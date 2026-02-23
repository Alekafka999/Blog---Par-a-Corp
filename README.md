# Blog PHP Minimalista (sem banco de dados)

Projeto simples de blog em PHP puro, com frontend, login, painel administrativo, upload de imagem e armazenamento em arquivo JSON.

## Arquivos principais

- `index.php`: frontend
- `login.php`: tela de login (centralizada)
- `admin.php`: backend para publicar artigos
- `config.php`: configurações e credenciais
- `data/posts.json`: posts salvos
- `uploads/`: imagens enviadas

## Credenciais padrão

- Usuário: `admin`
- Senha: `admin123`

Troque em `config.php` antes de publicar.

## Requisitos

- PHP 8+
- Permissão de escrita em `data/` e `uploads/`

