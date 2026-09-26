#!/usr/bin/env python3
"""Gera o PDF da "Solicitação de Resolução de Não Conformidade" e envia o e-mail.

Chamado pelo PHP (config/email.php) na própria requisição. Recebe um JSON no stdin e
responde um JSON no stdout:
  entrada: {"smtp": {...}, "email": {...}, "documento": {...}, "anexo_nome": "...",
            "apenas_pdf": false}
  saída:   {"ok": true, "pdf_base64": "..."}
           {"ok": false, "etapa": "entrada|pdf|smtp", "erro": "mensagem para o usuário",
            "detalhe": "mensagem técnica (vai só para o log)"}
Os dados chegam pelo stdin (e não por argumentos) para a senha SMTP não aparecer na
lista de processos.
"""

import base64
import io
import json
import smtplib
import socket
import ssl
import sys
from email.message import EmailMessage
from email.utils import formataddr, formatdate, make_msgid
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

TIMEOUT_SMTP = 20

# Cores e fontes do template "Solicitacao_Resolucao_Nao_Conformidade".
AZUL_TITULO = colors.HexColor("#1F3864")
AZUL_FAIXA = colors.HexColor("#2F5597")
CINZA_ROTULO = colors.HexColor("#E7E6E6")
CINZA_BORDA = colors.HexColor("#BFBFBF")

ESTILO_TITULO = ParagraphStyle("titulo", fontName="Times-Bold", fontSize=18, leading=22,
                               alignment=TA_CENTER, textColor=AZUL_TITULO)
ESTILO_SUBTITULO = ParagraphStyle("subtitulo", fontName="Times-Italic", fontSize=10, leading=13,
                                  alignment=TA_CENTER, textColor=colors.HexColor("#7F7F7F"))
ESTILO_TEXTO = ParagraphStyle("texto", fontName="Times-Roman", fontSize=10.5, leading=13.5)
ESTILO_ROTULO = ParagraphStyle("rotulo", parent=ESTILO_TEXTO, fontName="Times-Bold")
ESTILO_FAIXA = ParagraphStyle("faixa", parent=ESTILO_TEXTO, fontName="Times-Bold", textColor=colors.white)


def texto(valor, estilo=ESTILO_TEXTO):
    """Parágrafo com o texto escapado (o reportlab interpreta marcação) e quebras de linha."""
    return Paragraph(escape("" if valor is None else str(valor)).replace("\n", "<br/>"), estilo)


def tabela(linhas, larguras, estilos_extras=()):
    t = Table(linhas, colWidths=larguras)
    t.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.5, CINZA_BORDA),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        *estilos_extras,
    ]))
    return t


def secao(titulo, cabecalho, linhas, larguras):
    """Faixa azul com o título da seção + tabela com linha de cabeçalho cinza."""
    total = sum(larguras)
    faixa = tabela([[texto(titulo, ESTILO_FAIXA)]], [total], [("BACKGROUND", (0, 0), (-1, -1), AZUL_FAIXA)])
    corpo = [[texto(c, ESTILO_ROTULO) for c in cabecalho]] if cabecalho else []
    corpo += [[texto(c) for c in linha] for linha in linhas]
    extras = [("BACKGROUND", (0, 0), (-1, 0), CINZA_ROTULO)] if cabecalho else []
    return [faixa, tabela(corpo, larguras, extras), Spacer(1, 0.6 * cm)]


def gerar_pdf(doc):
    buffer = io.BytesIO()
    pdf = SimpleDocTemplate(buffer, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm,
                            topMargin=2 * cm, bottomMargin=2 * cm,
                            title="Solicitação de Resolução de Não Conformidade", author="DeepCheck")
    largura = A4[0] - 4 * cm

    cabecalho = [
        ("Projeto", doc["projeto"]),
        ("Responsável pela Resolução", doc["responsavel_resolucao"]),
        ("Responsável por QA", doc["responsavel_qa"]),
        ("Data da 1ª Solicitação", doc["data_primeira_solicitacao"]),
        ("Prazo de Resolução", doc["prazo_resolucao"]),
        ("Nº de Escalonamento", doc["numero_escalonamento"]),
    ]
    historico = doc.get("historico") or []
    linhas_historico = ([[h["superior"], h["responsavel"], h["prazo"]] for h in historico]
                        or [["Nenhum", "", ""]])

    elementos = [
        texto("Solicitação de Resolução de Não Conformidade", ESTILO_TITULO),
        Spacer(1, 0.15 * cm),
        texto("Template de Solicitação de Resolução de Não Conformidade — Versão 1.0", ESTILO_SUBTITULO),
        Spacer(1, 0.6 * cm),
        tabela([[texto(r, ESTILO_ROTULO), texto(v)] for r, v in cabecalho], [largura * 0.35, largura * 0.65],
               [("BACKGROUND", (0, 0), (0, -1), CINZA_ROTULO)]),
        Spacer(1, 0.6 * cm),
        *secao("Não Conformidade Identificada", ["Descrição", "Classificação", "Ação Corretiva Indicada"],
               [[doc["descricao"], doc["classificacao"], doc["acao_corretiva"]]],
               [largura * 0.42, largura * 0.2, largura * 0.38]),
        *secao("Histórico de Escalonamento", ["Superior", "Responsável", "Prazo para Resolução"],
               linhas_historico, [largura / 3] * 3),
        *secao("Observações", None, [[doc.get("observacoes") or "Nenhuma observação adicional."]], [largura]),
    ]
    pdf.build(elementos)
    return buffer.getvalue()


def montar_mensagem(email, pdf, anexo_nome):
    msg = EmailMessage()
    msg["From"] = formataddr((email["de_nome"], email["de_email"]))
    msg["To"] = formataddr((email["para_nome"], email["para"]))
    if email.get("cc"):
        msg["Cc"] = ", ".join(email["cc"])
    msg["Reply-To"] = email["responder_para"]
    msg["Subject"] = email["assunto"]
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid(domain=email["de_email"].split("@")[-1])
    msg.set_content(email["corpo"])
    msg.add_attachment(pdf, maintype="application", subtype="pdf", filename=anexo_nome)
    return msg


def enviar(smtp, msg):
    contexto = ssl.create_default_context()
    if smtp["seguranca"] == "ssl":
        servidor = smtplib.SMTP_SSL(smtp["host"], int(smtp["porta"]), timeout=TIMEOUT_SMTP, context=contexto)
    else:
        servidor = smtplib.SMTP(smtp["host"], int(smtp["porta"]), timeout=TIMEOUT_SMTP)
    with servidor:
        if smtp["seguranca"] == "starttls":
            servidor.starttls(context=contexto)
        if smtp.get("usuario"):
            servidor.login(smtp["usuario"], smtp["senha"])
        servidor.send_message(msg)


def responder(dados, codigo):
    print(json.dumps(dados, ensure_ascii=False))
    sys.exit(codigo)


def falha(etapa, erro, detalhe):
    responder({"ok": False, "etapa": etapa, "erro": erro, "detalhe": str(detalhe)[:500]}, 1)


def main():
    try:
        entrada = json.load(sys.stdin)
        documento = entrada["documento"]
        anexo_nome = entrada["anexo_nome"]
    except (ValueError, KeyError, TypeError) as e:
        falha("entrada", "Dados inválidos para gerar o documento.", e)

    try:
        pdf = gerar_pdf(documento)
    except Exception as e:  # noqa: BLE001 — qualquer falha do reportlab vira resposta de erro
        falha("pdf", "Não foi possível gerar o PDF da solicitação.", e)

    if not entrada.get("apenas_pdf"):
        try:
            enviar(entrada["smtp"], montar_mensagem(entrada["email"], pdf, anexo_nome))
        except smtplib.SMTPAuthenticationError as e:
            falha("smtp", "O servidor de e-mail recusou o login da conta do sistema (verifique SMTP_USUARIO e SMTP_SENHA).", e)
        except smtplib.SMTPRecipientsRefused as e:
            falha("smtp", "O servidor de e-mail recusou o endereço do destinatário.", e)
        except (smtplib.SMTPException, socket.timeout, OSError) as e:
            falha("smtp", "Não foi possível enviar o e-mail: falha ao falar com o servidor de e-mail.", e)
        except (KeyError, TypeError, ValueError) as e:
            falha("entrada", "Dados inválidos para montar o e-mail.", e)

    responder({"ok": True, "pdf_base64": base64.b64encode(pdf).decode("ascii")}, 0)


if __name__ == "__main__":
    main()
