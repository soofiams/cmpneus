<?php
require __DIR__ . '/_layout.php';

$u = utilizador();
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM utilizadores WHERE id = ?');
    $st->execute([$u['id']]);
    $atual = $st->fetch();
    $acao = $_POST['acao'] ?? '';

    if (!$atual || !password_verify($_POST['atual'] ?? '', $atual['password_hash'])) {
        $erro = 'A palavra-passe atual não está certa.';
    } elseif ($acao === 'dados') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indique o nome e um email válido.';
        } else {
            db()->prepare('UPDATE utilizadores SET nome = ?, email = ? WHERE id = ?')->execute([$nome, $email, $u['id']]);
            $_SESSION['utilizador']['nome'] = $nome;
            $_SESSION['utilizador']['email'] = $email;
            flash('Dados da conta guardados.');
            redirecionar('conta.php');
        }
    } elseif ($acao === 'pass') {
        $nova = $_POST['nova'] ?? '';
        if (mb_strlen($nova) < 8) {
            $erro = 'A nova palavra-passe tem de ter pelo menos 8 caracteres.';
        } elseif ($nova !== ($_POST['repetir'] ?? '')) {
            $erro = 'As duas palavras-passe novas não são iguais.';
        } else {
            db()->prepare('UPDATE utilizadores SET password_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
            flash('Palavra-passe alterada.');
            redirecionar('conta.php');
        }
    } elseif ($acao === 'novo') {
        $nome = trim($_POST['n_nome'] ?? '');
        $email = trim($_POST['n_email'] ?? '');
        $pass = $_POST['n_pass'] ?? '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 8) {
            $erro = 'Para a nova conta indique nome, email válido e palavra-passe com 8 ou mais caracteres.';
        } else {
            try {
                db()->prepare('INSERT INTO utilizadores (nome, email, password_hash) VALUES (?,?,?)')->execute([$nome, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                flash("Conta de $nome criada.");
                redirecionar('conta.php');
            } catch (PDOException $e) {
                $erro = 'Já existe uma conta com esse email.';
            }
        }
    }
}
$contas = db()->query('SELECT nome, email, criado_em FROM utilizadores ORDER BY id')->fetchAll();

topo('A minha conta', 'conta');
?>
<div class="head"><div><h1>A minha conta</h1><p class="muted">Para qualquer alteração, confirme com a palavra-passe atual.</p></div></div>
<?php if ($erro): ?><p class="alert err"><?= h($erro) ?></p><?php endif; ?>

<div class="detail">
  <div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="acao" value="dados">
      <h2>Dados de acesso</h2>
      <div class="grid2">
        <label>Nome<input name="nome" required value="<?= h($u['nome']) ?>"></label>
        <label>Email<input name="email" type="email" required value="<?= h($u['email']) ?>"></label>
        <label class="full">Palavra-passe atual<input name="atual" type="password" required autocomplete="current-password"></label>
      </div>
      <div class="form-actions"><button class="btn primary">Guardar</button></div>
    </form>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="acao" value="pass">
      <h2>Mudar palavra-passe</h2>
      <div class="grid2">
        <label class="full">Palavra-passe atual<input name="atual" type="password" required autocomplete="current-password"></label>
        <label>Nova palavra-passe<input name="nova" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>Repetir nova<input name="repetir" type="password" required minlength="8" autocomplete="new-password"></label>
      </div>
      <div class="form-actions"><button class="btn primary">Mudar palavra-passe</button></div>
    </form>
  </div>
  <div>
    <div class="card">
      <h2>Contas com acesso</h2>
      <table class="list"><?php foreach ($contas as $c): ?><tr><td><b><?= h($c['nome']) ?></b><span class="sub"><?= h($c['email']) ?></span></td></tr><?php endforeach; ?></table>
    </div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="acao" value="novo">
      <h2>Dar acesso a outra pessoa</h2>
      <div class="stack">
        <label>Nome<input name="n_nome" required></label>
        <label>Email<input name="n_email" type="email" required></label>
        <label>Palavra-passe inicial<input name="n_pass" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>A sua palavra-passe atual<input name="atual" type="password" required autocomplete="current-password"></label>
        <div class="form-actions"><button class="btn">Criar conta</button></div>
      </div>
    </form>
  </div>
</div>
<?php fundo();
