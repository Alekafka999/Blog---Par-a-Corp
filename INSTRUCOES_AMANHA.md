# Instrucoes Para Continuar (24/02/2026)

## Status atual

- Blog PHP minimalista criado sem banco de dados.
- Persistencia em `data/posts.json`.
- Upload de imagens em `uploads/`.
- Frontend em `index.php`.
- Login em `login.php`.
- Backend/painel em `admin.php`.
- Logout em `logout.php`.
- Estilos em `style.css`.
- Sintaxe PHP validada com `php -l` (sem erros).

## Credenciais atuais

- Usuario: `admin`
- Senha: `admin123`

## Primeiro passo amanha (teste rapido)

1. Abrir o projeto no navegador via Laragon (`http://localhost/Blog/` ou equivalente).
2. Entrar em `Login`.
3. Publicar 2 ou 3 artigos com datas diferentes.
4. Confirmar no frontend:
   - ordem crescente por data
   - imagem aparecendo
   - calendario marcando dias com publicacao
   - filtro por dia ao clicar no calendario

## Ajustes recomendados (prioridade)

1. Trocar credenciais padrao em `config.php`.
2. Melhorar seguranca do login (hash de senha em vez de texto puro).
3. Adicionar editar/excluir post no `admin.php`.
4. Validar permissao de escrita nas pastas `data/` e `uploads/`.
5. Melhorar mensagens de erro/upload (tipos e tamanho de imagem).

## Melhorias opcionais

1. Campo de resumo/excerpt no post.
2. Campo de autor.
3. Categorias/tags.
4. Busca simples por titulo.
5. Paginacao no frontend.
6. Template mais personalizado para GitHub/community docs.

## Arquivos principais para revisar

- `config.php`
- `index.php`
- `admin.php`
- `includes/functions.php`
- `includes/auth.php`
- `style.css`

## Observacoes

- Se aparecer erro de upload, verificar configuracoes do PHP (`upload_max_filesize`, `post_max_size`) e permissoes da pasta `uploads/`.
- Se o calendario nao marcar datas corretamente, conferir o valor salvo em `published_at` dentro de `data/posts.json`.

