# Como lançar uma versão nova do One (sem mexer nos dados em uso)

**Por que é seguro:** os dados da equipe (clientes, contatos, metas, usuários, progresso na Academia) ficam no
**banco de dados**. Uma versão nova troca só os **arquivos do sistema** e, se precisar, **acrescenta** tabelas,
campos ou configurações no banco. Nada é apagado e nenhum dado de demonstração entra junto.

---

## No seu computador (com o Claude Code)

Peça: *"gere o pacote de atualização"*, ou rode:

```
bash ferramentas/gerar_pacote.sh --atualizacao
```

Sai o arquivo `pacote/one-atualizacao-AAAA.MM.DD-xxxxxxx.zip`. Ele **não** contém:
`config/config.php` (suas senhas), `referencias/` (dados de clientes) nem as mídias da Academia.

## Na hospedagem — 5 minutos

Faça de preferência fora do horário de uso da equipe.

1. **Backup** — no One, entre como admin › **Administração › Atualização** › **Baixar backup (.sql.gz)**.
   Guarde o arquivo (tem dados de clientes).
2. **Enviar** — cPanel › Gerenciador de arquivos › `public_html` › **Carregar** o zip de atualização.
3. **Extrair** — botão direito no zip › **Extrair** em `public_html`. Se perguntar, **substitua** os arquivos.
   Depois apague o zip.
4. **Aplicar** — volte ao One › **Administração › Atualização**.
   - Se aparecer "atualização(ões) pendente(s)", clique em **Aplicar atualização**.
   - Se aparecer "Banco em dia ✓", não há nada a fazer.
5. **Conferir** — o número da versão aparece no rodapé do menu. Abra a Carteira e o Meu mês para conferir.

## Se algo der errado (voltar atrás)

1. Envie e extraia de novo o **zip da versão anterior** (guarde sempre o último que funcionou).
2. Se o banco foi alterado: cPanel › **phpMyAdmin** › selecione o banco do One › **Importar** › envie o backup do passo 1.

## Recomendado: um ambiente de teste

Crie um segundo subdomínio (ex.: `teste-one.somosalquimia.com.br`) com outro banco, instalado com o pacote de
instalação. Antes de atualizar o oficial, aplique a versão nova no teste e confira. Assim a equipe nunca vê erro.

---

### O que nunca fazer

- Não use a tela `/instalar` num sistema em uso (ela recusa, por segurança). Versões novas: sempre pela tela **Atualização**.
- Não envie o **pacote de instalação** por cima do sistema em uso: ele traz um `config.php` novo, sem as senhas do banco.
- Não apague a pasta `public/media` (fotos e vídeos da Academia) nem `storage/`.
