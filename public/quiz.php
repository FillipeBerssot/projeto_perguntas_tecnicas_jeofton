<?php
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/questions.php';

require_login();

$area = $_GET['area'] ?? '';
$lang = $_GET['lang'] ?? '';

$allowed = [
  'Front-end' => ['JavaScript'],
  'Back-end'  => ['PHP','Python','SQL'],
  'Dados'     => ['SQL','Python'],
];

if (!isset($allowed[$area]) || !in_array($lang, $allowed[$area], true)) {
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <title>Combinação inválida</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container py-5">
      <div class="alert alert-warning">
        <strong>Ops!</strong> A combinação <em><?= h($area) ?> / <?= h($lang) ?></em> não é suportada neste MVP.
        <br>Permitidas:
        <ul class="mb-2">
          <li>Front-end: JavaScript</li>
          <li>Back-end: PHP, Python, SQL</li>
          <li>Dados: SQL, Python</li>
        </ul>
        <a class="btn btn-primary" href="/">Voltar e escolher outra combinação</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$questions = fetch_quiz($area, $lang);
if (count($questions) < 10) {
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <title>Quiz — insuficiente</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container py-5">
      <div class="alert alert-warning">
        Ainda não há 10 perguntas cadastradas para <strong><?= h($area) ?> / <?= h($lang) ?></strong>.
      </div>
      <a class="btn btn-secondary" href="/">Voltar</a>
    </body>
    </html>
    <?php
    exit;
}

$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Quiz - <?= h($area) ?> / <?= h($lang) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .question-card { transition: box-shadow .2s ease; }
    .question-card:hover { box-shadow: 0 0 0 .15rem rgba(13,110,253,.15); }
  </style>
</head>

<body class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="mb-0">Quiz</h1>
    <span class="text-muted"><?= h($area) ?> / <?= h($lang) ?></span>
  </div>

  <?php if ($err === 'incomplete'): ?>
    <div class="alert alert-warning">
      Responda <strong>todas</strong> as questões antes de enviar.
    </div>
  <?php endif; ?>

  <div class="mb-3">
    <div>Responda todas as questões e clique em <strong>Enviar e ver resultado</strong> para ver a pontuação e o gabarito das que errou.</div>
  </div>

  <div class="mb-3">
    <div class="d-flex justify-content-between">
      <span><strong>Progresso:</strong> <span id="answered-count">0</span>/<span id="total-count"><?= count($questions) ?></span></span>
      <span id="progress-label" class="text-muted">0%</span>
    </div>
    <div class="progress" style="height: 10px;">
      <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
    </div>
  </div>

  <form id="quiz-form" action="/result.php" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="area" value="<?= h($area) ?>">
    <input type="hidden" name="lang" value="<?= h($lang) ?>">

    <?php foreach ($questions as $idx => $q): ?>
      <input type="hidden" name="question_ids[]" value="<?= (int)$q['id'] ?>">
      <div class="card mb-4 question-card" data-question-id="<?= (int)$q['id'] ?>">
        <div class="card-body">
          <h5 class="card-title">Questão <?= $idx+1 ?>) <?= h($q['enunciado']) ?></h5>
          <div class="list-group">
            <?php foreach ($q['answers'] as $i => $ans): ?>
              <label class="list-group-item d-flex align-items-center my-1 p-2">
                <input
                  class="form-check-input me-2 question-radio"
                  type="radio"
                  name="answers[<?= (int)$q['id'] ?>]"
                  value="<?= (int)$ans['id'] ?>"
                  <?php if ($i === 0): ?>required<?php endif; ?> />
                <span><?= h($ans['texto']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2">
      <a href="/" class="btn btn-outline-secondary">Voltar</a>
      <button id="submit-btn" class="btn btn-success" disabled>Enviar e ver resultado</button>
    </div>
  </form>

<script>
function updateProgress() {
  const total = Number(document.getElementById('total-count').textContent);
  const answered = new Set();
  document.querySelectorAll('.question-card').forEach(card => {
    const checked = card.querySelector('input[type=radio]:checked');
    if (checked) answered.add(card.dataset.questionId);
  });
  const done = answered.size;
  const pct = total ? Math.round((done / total) * 100) : 0;
  document.getElementById('answered-count').textContent = done;
  document.getElementById('progress-label').textContent = pct + '%';
  document.getElementById('progress-bar').style.width = pct + '%';

  const submitBtn = document.getElementById('submit-btn');
  submitBtn.disabled = done !== total;
}

document.querySelectorAll('.question-radio').forEach(function(radio) {
  radio.addEventListener('change', updateProgress);
});

// Bloqueio extra no submit: se tiver faltando, impede e rola até a primeira
document.getElementById('quiz-form').addEventListener('submit', function(e){
  const total = Number(document.getElementById('total-count').textContent);
  let done = 0, firstUnanswered = null;
  document.querySelectorAll('.question-card').forEach(card => {
    const checked = card.querySelector('input[type=radio]:checked');
    if (checked) {
      done++;
    } else if (!firstUnanswered) {
      firstUnanswered = card;
    }
  });
  if (done !== total) {
    e.preventDefault();
    alert('Responda todas as questões antes de enviar.');
    if (firstUnanswered) firstUnanswered.scrollIntoView({behavior:'smooth', block:'center'});
  }
});

updateProgress();
</script>
</body>
</html>