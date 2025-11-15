<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php'; 

require_login();
$user = current_user();

/* ---------- SHIM: define update_profile() se o seu auth.php não tiver ---------- */
if (!function_exists('update_profile')) {
  /**
   * Atualiza o nome e, opcionalmente, a senha do usuário.
   * @param int $userId
   * @param string $name
   * @param ?string $newPassword  Se null ou '', não altera a senha
   * @return bool
   */
  function update_profile(int $userId, string $name, ?string $newPassword = null): bool {
    $pdo = getPDO();
    try {
      if ($newPassword !== null && $newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, password_hash = ? WHERE id = ?");
        return (bool)$stmt->execute([$name, $hash, $userId]);
      } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        return (bool)$stmt->execute([$name, $userId]);
      }
    } catch (Throwable $e) {
      return false;
    }
  }
}
/* ----------------------------------------------------------------------------- */

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $name        = trim($_POST['name'] ?? '');
  $new_pass    = $_POST['new_password'] ?? '';
  $confirm_new = $_POST['new_password_confirm'] ?? '';

  if ($name === '') {
    $errors[] = 'Informe seu nome.';
  }

  if ($new_pass !== '' || $confirm_new !== '') {
    if (strlen($new_pass) < 6) {
      $errors[] = 'A nova senha deve ter ao menos 6 caracteres.';
    } elseif ($new_pass !== $confirm_new) {
      $errors[] = 'A confirmação da senha não confere.';
    }
  }

  if (!$errors) {
    $ok = update_profile((int)$user['id'], $name, ($new_pass !== '' ? $new_pass : null));
    if ($ok) {
      $success = true;

      try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([(int)$user['id']]);
        $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fresh) { $user = $fresh; }
        else { $user['name'] = $name; } 
      } catch (Throwable $e) {
        $user['name'] = $name; 
      }
    } else {
      $errors[] = 'Não foi possível salvar as alterações.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Perfil — <?= h($config['app_name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php"><?= h($config['app_name']) ?></a>
    <div class="d-flex align-items-center">
      <span class="me-3">Olá, <?= h($user['name']) ?></span>
      <a class="btn btn-outline-danger btn-sm" href="logout.php">Sair</a>
    </div>
  </div>
</nav>

<main class="container pt-navbar" style="max-width: 860px;">
  <div class="card">
    <div class="card-body p-4 p-lg-5">
      <h1 class="h3 mb-3">Seu perfil</h1>
      <p class="text-muted mb-4">Atualize seu nome e, se quiser, altere sua senha.</p>

      <?php if ($success): ?>
        <div class="alert alert-success">Perfil atualizado com sucesso.</div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?>
            <div><?= h($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome</label>
            <input type="text" class="form-control" name="name" value="<?= h($user['name']) ?>" required maxlength="80">
          </div>
          <div class="col-md-6">
            <label class="form-label">E-mail</label>
            <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled>
          </div>
        </div>

        <hr class="my-4">

        <div class="mb-2">
          <span class="fw-semibold">Alterar senha (opcional)</span>
          <div class="text-muted small">Preencha os campos abaixo somente se desejar atualizar sua senha.</div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nova senha</label>
            <div class="input-group">
              <input id="new-pass" type="password" class="form-control" name="new_password" minlength="6">
              <button class="btn btn-outline-secondary" type="button" onclick="togglePass('new-pass', this)">Mostrar</button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar nova senha</label>
            <input id="new-pass2" type="password" class="form-control" name="new_password_confirm" minlength="6">
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
          <button class="btn btn-success">Salvar alterações</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
function togglePass(id, btn){
  const i = document.getElementById(id);
  const is = i.type === 'password';
  i.type = is ? 'text' : 'password';
  btn.textContent = is ? 'Ocultar' : 'Mostrar';
}
</script>
</body>
</html>