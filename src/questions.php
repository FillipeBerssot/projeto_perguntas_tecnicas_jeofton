<?php
// src/questions.php
require_once __DIR__ . '/db.php';

/** Util: checa se uma tabela possui TODAS as colunas listadas */
function table_has_columns(PDO $pdo, string $table, array $cols): bool {
    $stmt = $pdo->prepare("PRAGMA table_info($table)");
    $stmt->execute();
    $found = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $found[strtolower($r['name'])] = true;
    }
    foreach ($cols as $c) {
        if (empty($found[strtolower($c)])) return false;
    }
    return true;
}

/**
 * Busca 10 questões aleatórias para a combinação área/lang.
 * Retorna cada questão com suas alternativas.
 */
function fetch_quiz(string $area, string $lang): array {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT id, enunciado
        FROM questions
        WHERE area = ? AND lang = ?
        ORDER BY RANDOM()
        LIMIT 10
    ");
    $stmt->execute([$area, $lang]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$questions) return [];

    $ids = array_map(fn($q) => (int)$q['id'], $questions);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $stmtA = $pdo->prepare("SELECT id, question_id, texto, correta FROM answers WHERE question_id IN ($in) ORDER BY id");
    $stmtA->execute($ids);

    $ansByQ = [];
    while ($a = $stmtA->fetch(PDO::FETCH_ASSOC)) {
        $qid = (int)$a['question_id'];
        $ansByQ[$qid][] = [
            'id'      => (int)$a['id'],
            'texto'   => $a['texto'],
            'correta' => (int)$a['correta'],
        ];
    }

    $out = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $out[] = [
            'id'        => $qid,
            'enunciado' => $q['enunciado'],
            'answers'   => $ansByQ[$qid] ?? [],
        ];
    }
    return $out;
}

/**
 * Salva a tentativa do usuário.
 * $answers é um array no formato [question_id => answer_id].
 * Retorna o attempt_id.
 */
function save_attempt(int $userId, string $area, string $lang, array $answers): int {
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // 1) cria a tentativa com score 0
        $ins = $pdo->prepare("INSERT INTO attempts (user_id, area, lang, score, created_at) VALUES (?, ?, ?, 0, datetime('now'))");
        $ins->execute([$userId, $area, $lang]);
        $attemptId = (int)$pdo->lastInsertId();

        if ($answers) {
            // QIDs envolvidos
            $qids = array_map('intval', array_keys($answers));
            $inQs = implode(',', array_fill(0, count($qids), '?'));

            // 2) mapa de questões: id => enunciado
            $stQ = $pdo->prepare("SELECT id, enunciado FROM questions WHERE id IN ($inQs)");
            $stQ->execute($qids);
            $qText = [];
            while ($row = $stQ->fetch(PDO::FETCH_ASSOC)) {
                $qText[(int)$row['id']] = $row['enunciado'];
            }

            // 3) respostas por questão (id => [answer_id => ['texto'=>, 'correta'=>]])
            $stA = $pdo->prepare("SELECT id, question_id, texto, correta FROM answers WHERE question_id IN ($inQs)");
            $stA->execute($qids);
            $ansMap = [];
            $correctTextByQ = [];
            while ($row = $stA->fetch(PDO::FETCH_ASSOC)) {
                $qid = (int)$row['question_id'];
                $aid = (int)$row['id'];
                $ansMap[$qid][$aid] = [
                    'texto'   => $row['texto'],
                    'correta' => (int)$row['correta'],
                ];
                if ((int)$row['correta'] === 1) {
                    $correctTextByQ[$qid] = $row['texto'];
                }
            }

            // 4) decidir qual insert usar (schema com colunas extras ou básico)
            $hasExtraCols = table_has_columns($pdo, 'attempt_answers', ['enunciado', 'chosen_text', 'correct_text']);

            if ($hasExtraCols) {
                $insAA = $pdo->prepare("
                    INSERT INTO attempt_answers
                      (attempt_id, question_id, answer_id, correta, enunciado, chosen_text, correct_text)
                    VALUES
                      (?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $insAA = $pdo->prepare("
                    INSERT INTO attempt_answers
                      (attempt_id, question_id, answer_id, correta)
                    VALUES
                      (?, ?, ?, ?)
                ");
            }

            // 5) computar score e inserir linhas
            $score = 0;
            foreach ($answers as $qidRaw => $aidRaw) {
                $qid = (int)$qidRaw;
                $aid = (int)$aidRaw;

                $isCorrect = (isset($ansMap[$qid][$aid]) && $ansMap[$qid][$aid]['correta'] === 1) ? 1 : 0;
                if ($isCorrect === 1) $score++;

                if ($hasExtraCols) {
                    $enunciado    = $qText[$qid]             ?? '';
                    $chosenText   = $ansMap[$qid][$aid]['texto'] ?? '';
                    $correctText  = $correctTextByQ[$qid]    ?? '';
                    $insAA->execute([$attemptId, $qid, $aid, $isCorrect, $enunciado, $chosenText, $correctText]);
                } else {
                    $insAA->execute([$attemptId, $qid, $aid, $isCorrect]);
                }
            }

            // 6) atualiza score da tentativa
            $up = $pdo->prepare("UPDATE attempts SET score = ? WHERE id = ?");
            $up->execute([$score, $attemptId]);
        }

        $pdo->commit();
        return $attemptId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Carrega detalhes da tentativa para exibição no resultado.
 * Retorna:
 * [
 *   'attempt' => ['area','lang','score','created_at', ...],
 *   'answers' => [
 *      ['enunciado','chosen_text','correct_text','correta'=>0|1, ...],
 *      ...
 *   ]
 * ]
 */
function load_attempt_detail(int $attemptId): ?array {
    $pdo = getPDO();

    $stA = $pdo->prepare("SELECT * FROM attempts WHERE id = ?");
    $stA->execute([$attemptId]);
    $attempt = $stA->fetch(PDO::FETCH_ASSOC);
    if (!$attempt) return null;

    // Tenta caminho "tabela rica" primeiro (quando attempt_answers tem textos)
    $hasExtraCols = table_has_columns($pdo, 'attempt_answers', ['enunciado', 'chosen_text', 'correct_text']);
    if ($hasExtraCols) {
        $st = $pdo->prepare("
            SELECT enunciado, chosen_text, correct_text, correta
            FROM attempt_answers
            WHERE attempt_id = ?
            ORDER BY id
        ");
        $st->execute([$attemptId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return [
            'attempt' => $attempt,
            'answers' => $rows,
        ];
    }

    // Fallback: junta pelas tabelas base para montar os textos
    $st = $pdo->prepare("
        SELECT
            q.enunciado,
            aa.correta,
            ca.texto AS chosen_text,
            ga.texto AS correct_text
        FROM attempt_answers aa
        JOIN questions q ON q.id = aa.question_id
        JOIN answers   ca ON ca.id = aa.answer_id
        JOIN answers   ga ON ga.question_id = q.id AND ga.correta = 1
        WHERE aa.attempt_id = ?
        ORDER BY aa.id
    ");
    $st->execute([$attemptId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return [
        'attempt' => $attempt,
        'answers' => $rows,
    ];
}