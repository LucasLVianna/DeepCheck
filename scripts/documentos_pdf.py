#!/usr/bin/env python3
"""Gera os PDFs de exportação do DeepCheck: Plano de Garantia da Qualidade e Checklist.

Chamado pelo PHP (src/Controllers/projeto_exportar.php via config/python.php). Recebe um
JSON no stdin e responde um JSON no stdout:
  entrada: {"tipo": "pgq" | "checklist", "dados": {...}}
  saída:   {"ok": true, "pdf_base64": "..."}
           {"ok": false, "erro": "mensagem para o usuário", "detalhe": "técnico (log)"}
Os textos chegam já formatados pelo PHP (datas, rótulos); aqui só há layout.
"""

import base64
import io
import json
import sys
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas as canvas_mod
from reportlab.platypus import (Image, KeepTogether, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table,
                                TableStyle)

# Mesma identidade do PDF da Solicitação de NC (scripts/enviar_email.py).
AZUL_TITULO = colors.HexColor("#1F3864")
AZUL_FAIXA = colors.HexColor("#2F5597")
AZUL_CLARO = colors.HexColor("#DCE6F5")
CINZA_ROTULO = colors.HexColor("#E7E6E6")
CINZA_BORDA = colors.HexColor("#BFBFBF")
CINZA_TEXTO = colors.HexColor("#595959")
VERDE_CLARO = colors.HexColor("#E2F0D9")
VERMELHO_CLARO = colors.HexColor("#FBE3E3")
VERMELHO = colors.HexColor("#C00000")
VERDE = colors.HexColor("#2E7D32")
AMARELO_CLARO = colors.HexColor("#FFF2CC")

ESTILOS = {
    "capa_titulo": ParagraphStyle("capa_titulo", fontName="Helvetica-Bold", fontSize=26, leading=32, alignment=TA_CENTER),
    "capa_projeto": ParagraphStyle("capa_projeto", fontName="Helvetica-BoldOblique", fontSize=20, leading=26,
                                   alignment=TA_CENTER, textColor=AZUL_FAIXA),
    "capa_autor": ParagraphStyle("capa_autor", fontName="Helvetica-Oblique", fontSize=15, leading=20,
                                 alignment=TA_CENTER, textColor=AZUL_FAIXA),
    "capa_versao": ParagraphStyle("capa_versao", fontName="Helvetica-Bold", fontSize=15, leading=20, alignment=TA_CENTER),
    "capa_local": ParagraphStyle("capa_local", fontName="Helvetica-Oblique", fontSize=13, leading=18,
                                 alignment=TA_CENTER, textColor=AZUL_FAIXA),
    "h1": ParagraphStyle("h1", fontName="Helvetica-Bold", fontSize=15, leading=19, textColor=AZUL_TITULO,
                         spaceBefore=14, spaceAfter=6),
    "h2": ParagraphStyle("h2", fontName="Helvetica-Bold", fontSize=12, leading=15, textColor=AZUL_TITULO,
                         spaceBefore=8, spaceAfter=4),
    "texto": ParagraphStyle("texto", fontName="Helvetica", fontSize=10, leading=14),
    "texto_vazio": ParagraphStyle("texto_vazio", fontName="Helvetica-Oblique", fontSize=10, leading=14, textColor=CINZA_TEXTO),
    "celula": ParagraphStyle("celula", fontName="Helvetica", fontSize=9, leading=11.5),
    "celula_cab": ParagraphStyle("celula_cab", fontName="Helvetica-Bold", fontSize=9, leading=11.5, textColor=colors.white),
    "indice": ParagraphStyle("indice", fontName="Helvetica", fontSize=11, leading=18),
    "indice_sub": ParagraphStyle("indice_sub", fontName="Helvetica", fontSize=10, leading=16, leftIndent=18),
    # Checklist (paisagem, tabela larga)
    "ck_titulo": ParagraphStyle("ck_titulo", fontName="Helvetica-Bold", fontSize=17, leading=21, textColor=AZUL_TITULO),
    "ck_sub": ParagraphStyle("ck_sub", fontName="Helvetica", fontSize=10, leading=13, textColor=CINZA_TEXTO),
    "ck_celula": ParagraphStyle("ck_celula", fontName="Helvetica", fontSize=7.3, leading=9),
    "ck_celula_cab": ParagraphStyle("ck_celula_cab", fontName="Helvetica-Bold", fontSize=7.3, leading=9, textColor=colors.white),
    "ind_valor": ParagraphStyle("ind_valor", fontName="Helvetica-Bold", fontSize=15, leading=18, alignment=TA_CENTER),
    "ind_valor_destaque": ParagraphStyle("ind_valor_destaque", fontName="Helvetica-Bold", fontSize=20, leading=24,
                                         alignment=TA_CENTER, textColor=AZUL_FAIXA),
    "ind_rotulo": ParagraphStyle("ind_rotulo", fontName="Helvetica", fontSize=7.5, leading=9.5, alignment=TA_CENTER,
                                 textColor=CINZA_TEXTO),
    "legenda": ParagraphStyle("legenda", fontName="Helvetica", fontSize=8, leading=11, textColor=CINZA_TEXTO),
}


def p(texto, estilo="texto", vazio="—"):
    """Parágrafo com texto escapado (o reportlab interpreta marcação) e quebras de linha."""
    texto = "" if texto is None else str(texto)
    if texto.strip() == "":
        return Paragraph(escape(vazio), ESTILOS["texto_vazio"] if estilo == "texto" else ESTILOS[estilo])
    return Paragraph(escape(texto).replace("\n", "<br/>"), ESTILOS[estilo])


def tabela(linhas, larguras, cabecalho=True, extras=(), repetir=1):
    t = Table(linhas, colWidths=larguras, repeatRows=repetir if cabecalho else 0)
    estilo = [
        ("GRID", (0, 0), (-1, -1), 0.5, CINZA_BORDA),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ]
    if cabecalho:
        estilo += [("BACKGROUND", (0, 0), (-1, 0), AZUL_FAIXA), ("VALIGN", (0, 0), (-1, 0), "MIDDLE")]
    t.setStyle(TableStyle(estilo + list(extras)))
    return t


def canvas_numerado(titulo_cabecalho, gerado_em, pular_primeira):
    """Canvas que escreve cabeçalho e 'Página N de M' (precisa saber o total: 2 passagens)."""

    class CanvasNumerado(canvas_mod.Canvas):
        def __init__(self, *args, **kwargs):
            super().__init__(*args, **kwargs)
            self._paginas = []

        def showPage(self):
            self._paginas.append(dict(self.__dict__))
            self._startPage()

        def save(self):
            total = len(self._paginas)
            for estado in self._paginas:
                self.__dict__.update(estado)
                if not (pular_primeira and self._pageNumber == 1):
                    self._decorar(total)
                super().showPage()
            super().save()

        def _decorar(self, total):
            largura, altura = self._pagesize
            self.saveState()
            self.setStrokeColor(CINZA_BORDA)
            self.setLineWidth(0.5)
            self.line(1.5 * cm, altura - 1.25 * cm, largura - 1.5 * cm, altura - 1.25 * cm)
            self.line(1.5 * cm, 1.25 * cm, largura - 1.5 * cm, 1.25 * cm)
            self.setFont("Helvetica", 8)
            self.setFillColor(CINZA_TEXTO)
            self.drawString(1.5 * cm, altura - 1.05 * cm, titulo_cabecalho[:140])
            self.drawString(1.5 * cm, 0.85 * cm, f"Gerado pelo DeepCheck em {gerado_em}")
            self.drawRightString(largura - 1.5 * cm, 0.85 * cm, f"Página {self._pageNumber} de {total}")
            self.restoreState()

    return CanvasNumerado


def imagem_logo(logo, largura_max, altura_max):
    """Logo (base64) redimensionado mantendo a proporção; None se não houver ou for inválido."""
    if not logo:
        return None
    try:
        dados = base64.b64decode(logo)
        largura, altura = ImageReader(io.BytesIO(dados)).getSize()
        escala = min(largura_max / largura, altura_max / altura, 1)
        return Image(io.BytesIO(dados), width=largura * escala, height=altura * escala)
    except Exception:  # noqa: BLE001 — logo inválido não impede o documento
        return None


# --------------------------------------------------------------------------- PGQ

def gerar_pgq(d):
    buffer = io.BytesIO()
    doc = SimpleDocTemplate(buffer, pagesize=A4, leftMargin=2.2 * cm, rightMargin=2.2 * cm,
                            topMargin=2 * cm, bottomMargin=2 * cm,
                            title=f"Plano de Garantia da Qualidade - {d['projeto']}", author="DeepCheck")
    largura = A4[0] - 4.4 * cm
    e = []

    # Capa (como no template: logo, título, projeto, autor, versão; cidade e mês/ano embaixo)
    logo = imagem_logo(d.get("logo"), 6 * cm, 3.5 * cm)
    e.append(logo if logo else Spacer(1, 3.5 * cm))
    e += [Spacer(1, 4.5 * cm), p("Plano de Garantia da Qualidade", "capa_titulo"), Spacer(1, 0.8 * cm),
          p(d["projeto"], "capa_projeto"), Spacer(1, 0.5 * cm),
          p(d.get("autor_gqa"), "capa_autor", vazio="Autor/Responsável por GQA não informado"), Spacer(1, 0.6 * cm),
          p(f"Versão {d['versao_documento']}" if d.get("versao_documento") else "", "capa_versao", vazio=" "),
          Spacer(1, 7 * cm), p(d.get("local_data"), "capa_local", vazio=" "), PageBreak()]

    # Comprometimento
    e.append(p("Comprometimento", "h1"))
    comprometimento = [[p("Responsabilidade", "celula_cab"), p("Nome", "celula_cab"), p("Data", "celula_cab"),
                        p("Assinatura", "celula_cab")]]
    for linha in d["comprometimento"]:
        comprometimento.append([p(linha["papel"], "celula"), p(linha["nome"], "celula", " "),
                                p(linha["data"], "celula", " "), ""])
    e.append(tabela(comprometimento, [largura * 0.27, largura * 0.33, largura * 0.15, largura * 0.25],
                    extras=[("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white]),
                            ("BOTTOMPADDING", (0, 1), (-1, -1), 16), ("TOPPADDING", (0, 1), (-1, -1), 16)]))

    # Índice
    e += [Spacer(1, 1 * cm), p("Índice", "h1")]
    for titulo, sub in [("1. Introdução", ["1.1 Objetivo", "1.2 Visão Geral"]),
                        ("2. Documentação, Padrões e Diretrizes", []), ("3. Itens a Serem Avaliados", []),
                        ("4. Plano de Avaliações", []), ("5. Registros de Qualidade", []),
                        ("6. Definição das Não-Conformidades", []), ("7. Processo de escalonamento", [])]:
        e.append(p(titulo, "indice"))
        e += [p(s, "indice_sub") for s in sub]
    e.append(PageBreak())

    # 1. Introdução
    e += [p("1. Introdução", "h1"), p("1.1 Objetivo", "h2"), p(d.get("objetivo")),
          p("1.2 Visão Geral", "h2"), p(d.get("visao_geral"))]

    def secao_tabela(titulo, descricao, colunas, pesos, linhas):
        bloco = [p(titulo, "h1"), p(descricao), Spacer(1, 0.25 * cm)]
        if linhas:
            corpo = [[p(c, "celula_cab") for c in colunas]] + [[p(v, "celula", "—") for v in linha] for linha in linhas]
            bloco.append(tabela(corpo, [largura * peso for peso in pesos],
                                extras=[("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F5F8FC")])]))
        else:
            bloco.append(p("", vazio="Nenhum registro."))
        return bloco

    e += secao_tabela("2. Documentação, Padrões e Diretrizes",
                      f"Documentação, padrões e diretrizes utilizados no desenvolvimento do projeto {d['projeto']}, "
                      "para atender aos objetivos de qualidade estabelecidos para este projeto.",
                      ["Documento", "Versão"], [0.75, 0.25], d["documentos"])
    e += secao_tabela("3. Itens a Serem Avaliados",
                      f"Documentos do projeto {d['projeto']} que serão objetos de avaliação, indicando o local de armazenamento.",
                      ["Documento", "Local de Armazenamento", "Versão"], [0.35, 0.47, 0.18], d["itens_avaliados"])
    e += secao_tabela("4. Plano de Avaliações", "Avaliações que serão realizadas.",
                      ["Artefatos Avaliados", "Data da Avaliação", "Auditor"], [0.5, 0.2, 0.3], d["plano_avaliacoes"])

    e += [p("5. Registros de Qualidade", "h1"),
          p(f"Os registros das auditorias de qualidade para o projeto {d['projeto']} serão armazenados em:"),
          Spacer(1, 0.15 * cm), p(d.get("registros_qualidade_local"))]

    classificacoes = [[p("Classificação", "celula_cab"), p("Prazo de resolução", "celula_cab")]]
    classificacoes += [[p(c["nome"], "celula"), p(c["prazo"], "celula")] for c in d["classificacoes"]]
    e += [p("6. Definição das Não-Conformidades", "h1"),
          p("Gravidade de cada tipo de não conformidade e prazo de resolução (em dias úteis: sem sábados, "
            "domingos e feriados nacionais):"), Spacer(1, 0.25 * cm),
          tabela(classificacoes, [largura * 0.5, largura * 0.5]), Spacer(1, 0.3 * cm), p(d.get("definicao_nc_texto"))]

    e += [p("7. Processo de escalonamento", "h1"), p(d.get("processo_escalonamento_texto"))]

    doc.build(e, canvasmaker=canvas_numerado(f"Plano de Garantia da Qualidade — {d['projeto']}", d["gerado_em"], True))
    return buffer.getvalue()


# --------------------------------------------------------------------- Checklist

CORES_RESULTADO = {"conforme": VERDE_CLARO, "nao_conformidade": VERMELHO_CLARO, "nao_se_aplica": CINZA_ROTULO}
CORES_STATUS = {"pendente": AMARELO_CLARO, "escalonada": colors.HexColor("#FFE0B2"),
                "nao_resolvida": VERMELHO_CLARO, "resolvida": VERDE_CLARO, "fechada_por_excecao": CINZA_ROTULO}


def gerar_checklist(d):
    buffer = io.BytesIO()
    pagina = landscape(A4)
    doc = SimpleDocTemplate(buffer, pagesize=pagina, leftMargin=1.5 * cm, rightMargin=1.5 * cm,
                            topMargin=1.8 * cm, bottomMargin=1.7 * cm,
                            title=f"{d['nome']} - {d['projeto']}", author="DeepCheck")
    largura = pagina[0] - 3 * cm
    e = [p(d["nome"], "ck_titulo"), p(f"Projeto: {d['projeto']}", "ck_sub"), Spacer(1, 0.35 * cm)]

    # Painel de indicadores (como a linha de totais da planilha)
    ind = d["indicadores"]
    caixas = [("Aderência", ind["aderencia"], True), ("Total de itens (NT)", ind["nt"], False),
              ("Não avaliados (NA)", ind["na"], False), ("Avaliados (NTA)", ind["nta"], False),
              ("Conformidades (NC)", ind["nc"], False), ("Não conformidades (NNC)", ind["nnc"], False),
              ("Não aplicáveis", ind["nna"], False)]
    painel = Table([[p(v, "ind_valor_destaque" if destaque else "ind_valor") for _, v, destaque in caixas],
                    [p(r, "ind_rotulo") for r, _, _ in caixas]],
                   colWidths=[largura * 0.19] + [largura * 0.135] * 6)
    painel.setStyle(TableStyle([
        ("BOX", (0, 0), (-1, -1), 0.5, CINZA_BORDA),
        ("LINEAFTER", (0, 0), (-2, -1), 0.5, CINZA_BORDA),
        ("BACKGROUND", (0, 0), (0, -1), AZUL_CLARO),
        ("TOPPADDING", (0, 0), (-1, 0), 7), ("BOTTOMPADDING", (0, -1), (-1, -1), 7),
    ]))
    e += [painel, Spacer(1, 0.4 * cm)]

    colunas = ["Nº", "Descrição", "Resultado", "Data e hora da identificação da NC", "Responsável pela resolução",
               "Classificação da NC", "Ação corretiva indicada", "Data prevista de resolução",
               "Data e hora do escalonamento", "Data e hora da conclusão da NC", "Status da NC"]
    pesos = [0.03, 0.17, 0.085, 0.08, 0.085, 0.085, 0.13, 0.075, 0.095, 0.095, 0.07]
    linhas = [[p(c, "ck_celula_cab") for c in colunas]]
    extras = []
    for i, item in enumerate(d["itens"], start=1):
        prevista = p(item["data_prevista"], "ck_celula", "")
        if item["atrasado"]:
            prevista = Paragraph(f'<font color="#C00000"><b>{escape(item["data_prevista"])}</b></font>', ESTILOS["ck_celula"])
        linhas.append([p(item["numero"], "ck_celula"), p(item["descricao"], "ck_celula"),
                       p(item["resultado_rotulo"], "ck_celula"), p(item["data_identificacao"], "ck_celula", ""),
                       p(item["responsavel"], "ck_celula", ""), p(item["classificacao"], "ck_celula", ""),
                       p(item["acao"], "ck_celula", ""), prevista, p(item["data_escalonamento"], "ck_celula", ""),
                       p(item["data_conclusao"], "ck_celula", ""), p(item["status_rotulo"], "ck_celula", "")])
        if item["resultado"] in CORES_RESULTADO:
            extras.append(("BACKGROUND", (2, i), (2, i), CORES_RESULTADO[item["resultado"]]))
        if item["status"] in CORES_STATUS:
            extras.append(("BACKGROUND", (10, i), (10, i), CORES_STATUS[item["status"]]))
    if len(linhas) == 1:
        e.append(p("", vazio="O checklist ainda não tem itens."))
    else:
        e.append(tabela(linhas, [largura * peso for peso in pesos], extras=extras))

    legenda = [
        p("Fórmula: NTA = NT − NA − não aplicáveis · NC = NTA − NNC · Aderência = NC / NTA × 100. "
          "NC resolvida conta como conformidade; NC fechada por exceção conta como não aplicável.", "legenda"),
        p("Classificações: " + " · ".join(f"{c['nome']} | {c['prazo']}" for c in d["classificacoes"])
          + " (prazos em dias úteis: sem sábados, domingos e feriados nacionais). Data prevista em vermelho = prazo vencido.",
          "legenda"),
    ]
    e += [Spacer(1, 0.35 * cm), KeepTogether(legenda)]

    doc.build(e, canvasmaker=canvas_numerado(f"{d['nome']} — {d['projeto']}", d["gerado_em"], False))
    return buffer.getvalue()


def main():
    try:
        entrada = json.load(sys.stdin)
        gerador = {"pgq": gerar_pgq, "checklist": gerar_checklist}[entrada["tipo"]]
        pdf = gerador(entrada["dados"])
    except (ValueError, KeyError, TypeError) as e:
        print(json.dumps({"ok": False, "erro": "Dados inválidos para gerar o PDF.", "detalhe": str(e)[:500]}))
        sys.exit(1)
    except Exception as e:  # noqa: BLE001
        print(json.dumps({"ok": False, "erro": "Não foi possível gerar o PDF.", "detalhe": f"{type(e).__name__}: {e}"[:500]}))
        sys.exit(1)
    print(json.dumps({"ok": True, "pdf_base64": base64.b64encode(pdf).decode("ascii")}))


if __name__ == "__main__":
    main()
