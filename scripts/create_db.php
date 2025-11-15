<?php
require_once __DIR__ . '/../src/db.php';

$pdo = getPDO();

/* =========================
   SCHEMA
========================= */
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS questions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  area TEXT NOT NULL,       -- 'Back-end' | 'Front-end' | 'Dados'
  lang TEXT NOT NULL,       -- 'PHP' | 'JavaScript' | 'Python' | 'SQL'
  enunciado TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS answers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL,
  texto TEXT NOT NULL,
  correta INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  area TEXT NOT NULL,
  lang TEXT NOT NULL,
  score INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- inclui colunas de revisão usadas no result.php
CREATE TABLE IF NOT EXISTS attempt_answers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  attempt_id  INTEGER NOT NULL,
  question_id INTEGER NOT NULL,
  answer_id   INTEGER NOT NULL,
  correta     INTEGER NOT NULL,
  enunciado   TEXT NOT NULL,
  chosen_text TEXT NOT NULL,
  correct_text TEXT NOT NULL,
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
);
");

/* =========================
   HELPERS DE SEED
========================= */
function seed_block(PDO $pdo, string $area, string $lang, array $pairs): void {
    $pdo->beginTransaction();
    $insertQ = $pdo->prepare("INSERT INTO questions (area, lang, enunciado) VALUES (?, ?, ?)");
    $insertA = $pdo->prepare("INSERT INTO answers (question_id, texto, correta) VALUES (?, ?, ?)");
    foreach ($pairs as $enunciado => $alts) {
        $insertQ->execute([$area, $lang, $enunciado]);
        $qid = (int)$pdo->lastInsertId();
        for ($i = 0; $i < count($alts); $i += 2) {
            $insertA->execute([$qid, $alts[$i], $alts[$i+1] ? 1 : 0]);
        }
    }
    $pdo->commit();
}

/* =========================
   BASE (10) POR COMBINAÇÃO
========================= */

/* Back-end / PHP (10 base) */
$exists = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='PHP'")->fetchColumn();
if ((int)$exists === 0) {
    $qs = [
        "Qual função do PHP é usada para escapar HTML com segurança?" => ["htmlspecialchars()", true, "strip_tags()", false, "addslashes()", false, "htmlentities()", false],
        "Qual extensão do PHP é usada para interagir com bancos via objeto padrão?" => ["PDO", true, "mysqli", false, "pgsql", false, "odbc", false],
        "Qual comando inicia uma sessão no PHP?" => ["session_start()", true, "start_session()", false, "init_session()", false, "session_begin()", false],
        "Qual operador concatena strings no PHP?" => [".", true, "+", false, "*", false, "&", false],
        "Como declarar uma constante em PHP moderno?" => ["define('NOME', 'valor');", true, "const('NOME','valor');", false, "constant NOME='valor';", false, "let NOME='valor';", false],
        "Qual a forma segura de armazenar senhas?" => ["password_hash()", true, "md5()", false, "sha1()", false, "base64_encode()", false],
        "Qual superglobal contém dados enviados via formulário POST?" => ["\$_POST", true, "\$_GET", false, "\$_REQUEST", false, "\$_SERVER", false],
        "Qual modo de erro do PDO lança exceções?" => ["PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION", true, "PDO::EXCEPTIONS_ON", false, "PDO::THROW", false, "ERRMODE=1", false],
        "Como evitar SQL Injection no PDO?" => ["Prepared Statements (prepare/execute)", true, "Concatenando strings", false, "Escapando com addslashes", false, "Usando replace()", false],
        "Qual função verifica se uma senha em hash confere?" => ["password_verify()", true, "verify_hash()", false, "hash_equals()", false, "check_password()", false],
    ];
    seed_block($pdo, 'Back-end', 'PHP', $qs);
    echo "Seed Back-end/PHP base (10) criado.\n";
}

/* Front-end / JavaScript (10 base) */
$existsJs = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Front-end' AND lang='JavaScript'")->fetchColumn();
if ((int)$existsJs === 0) {
    $qs = [
        "Qual método seleciona o primeiro elemento que corresponde a um seletor CSS?" =>
            ["document.querySelector()", true, "document.getElement()", false, "document.query()", false, "document.select()", false],
        "Qual evento é disparado quando o usuário clica em um elemento?" =>
            ["click", true, "change", false, "submit", false, "hover", false],
        "Qual é a forma correta de declarar uma constante em JavaScript moderno?" =>
            ["const PI = 3.14;", true, "constant PI = 3.14;", false, "letconst PI = 3.14;", false, "varconst PI = 3.14;", false],
        "O que `===` faz em comparação a `==`?" =>
            ["Compara valor e tipo sem coerção", true, "Compara apenas o valor com coerção", false, "Compara apenas o tipo", false, "Concatena valores", false],
        "Qual método converte uma string em número inteiro?" =>
            ["parseInt('42', 10)", true, "Number.parseFloat('42')", false, "toInt('42')", false, "int('42')", false],
        "Como prevenir o envio padrão de um formulário em JS?" =>
            ["event.preventDefault()", true, "event.stop()", false, "form.prevent()", false, "return stopDefault()", false],
        "Qual propriedade altera o conteúdo textual de um elemento?" =>
            ["element.textContent", true, "element.innerStyle", false, "element.text()", false, "element.valueText", false],
        "Qual método adiciona um ouvinte de evento a um elemento?" =>
            ["addEventListener()", true, "onEvent()", false, "listen()", false, "attach()", false],
        "Qual escopo `let` possui?" =>
            ["Escopo de bloco", true, "Escopo global apenas", false, "Escopo de função apenas", false, "Sem escopo", false],
        "Qual é o valor de `typeof null` em JS?" =>
            ["'object'", true, "'null'", false, "'undefined'", false, "'number'", false],
    ];
    seed_block($pdo, 'Front-end', 'JavaScript', $qs);
    echo "Seed Front-end/JavaScript base (10) criado.\n";
}

/* Back-end / Python (10 base) */
$existsPy = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='Python'")->fetchColumn();
if ((int)$existsPy === 0) {
    $qs = [
        "Qual estrutura de dados é imutável em Python?" =>
            ["tuple", true, "list", false, "dict", false, "set", false],
        "Qual palavra-chave define uma função?" =>
            ["def", true, "func", false, "lambda:", false, "fn", false],
        "Qual método adiciona um item ao final de uma lista?" =>
            ["append()", true, "add()", false, "push()", false, "insertEnd()", false],
        "Qual operador verifica igualdade de valor em Python?" =>
            ["==", true, "===", false, "=", false, "eq", false],
        "O que `len()` retorna?" =>
            ["O tamanho/quantidade de itens", true, "O último índice", false, "A capacidade máxima", false, "O tipo do objeto", false],
        "Qual é a forma correta de abrir um arquivo para leitura?" =>
            ["open('arquivo.txt', 'r')", true, "open('arquivo.txt', 'w')", false, "read('arquivo.txt')", false, "file('arquivo.txt')", false],
        "Como converter string para inteiro?" =>
            ["int('123')", true, "parseInt('123')", false, "toInt('123')", false, "Integer('123')", false],
        "Qual compreensível cria lista de quadrados de 0 a 4?" =>
            ["[x*x for x in range(5)]", true, "[x^2 for x in 0..4]", false, "map(square, 0..4)", false, "range(5).map(x*x)", false],
        "Qual exceção padrão representa índice fora do intervalo?" =>
            ["IndexError", true, "KeyError", false, "ValueError", false, "TypeError", false],
        "Qual estrutura faz iteração com índice e valor?" =>
            ["enumerate(iterável)", true, "with index(iterável)", false, "iterate(iterável)", false, "range(iterável)", false],
    ];
    seed_block($pdo, 'Back-end', 'Python', $qs);
    echo "Seed Back-end/Python base (10) criado.\n";
}

/* Dados / SQL (10 base) */
$existsSql = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Dados' AND lang='SQL'")->fetchColumn();
if ((int)$existsSql === 0) {
    $qs = [
        "Qual comando retorna linhas de uma tabela?" =>
            ["SELECT", true, "INSERT", false, "UPDATE", false, "DELETE", false],
        "Qual cláusula filtra linhas após o FROM?" =>
            ["WHERE", true, "HAVING", false, "GROUP BY", false, "ORDER BY", false],
        "Qual função retorna a contagem de linhas?" =>
            ["COUNT(*)", true, "SUM(*)", false, "LEN(*)", false, "SIZE(*)", false],
        "Qual cláusula agrupa linhas por uma coluna?" =>
            ["GROUP BY", true, "ORDER BY", false, "PARTITION BY", false, "CLUSTER BY", false],
        "Qual cláusula ordena o resultado?" =>
            ["ORDER BY", true, "SORT BY", false, "ARRANGE BY", false, "ALIGN BY", false],
        "Qual operador combina linhas de duas tabelas com condição?" =>
            ["JOIN", true, "MERGE", false, "APPEND", false, "LINK", false],
        "Qual comando insere nova linha?" =>
            ["INSERT", true, "ADD", false, "CREATE", false, "PUT", false],
        "Qual comando altera valores existentes?" =>
            ["UPDATE", true, "ALTER", false, "CHANGE", false, "MODIFY", false],
        "Qual comando remove linhas?" =>
            ["DELETE", true, "DROP", false, "REMOVE", false, "ERASE", false],
        "Qual palavra reservada remove duplicados no SELECT?" =>
            ["DISTINCT", true, "UNIQUE", false, "ONLY", false, "NODUP", false],
    ];
    seed_block($pdo, 'Dados', 'SQL', $qs);
    echo "Seed Dados/SQL base (10) criado.\n";
}

/* Back-end / SQL (10 base) */
$existsBeSql = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='SQL'")->fetchColumn();
if ((int)$existsBeSql === 0) {
    $qs = [
        "Qual comando cria uma tabela?" =>
            ["CREATE TABLE", true, "MAKE TABLE", false, "NEW TABLE", false, "ADD TABLE", false],
        "Qual tipo de join retorna apenas linhas com correspondência em ambas as tabelas?" =>
            ["INNER JOIN", true, "LEFT JOIN", false, "RIGHT JOIN", false, "FULL JOIN", false],
        "Qual cláusula limita a quantidade de linhas retornadas?" =>
            ["LIMIT", true, "TOP", false, "ROWCOUNT", false, "OFFSET ONLY", false],
        "Qual índice acelera buscas por uma coluna?" =>
            ["INDEX", true, "TRIGGER", false, "VIEW", false, "SEQUENCE", false],
        "Qual comando adiciona uma coluna a uma tabela existente?" =>
            ["ALTER TABLE ... ADD COLUMN", true, "UPDATE TABLE ... ADD", false, "MODIFY TABLE ... COLUMN", false, "CHANGE TABLE ... ADD", false],
        "Qual função agrega soma de valores?" =>
            ["SUM(col)", true, "ADD(col)", false, "TOTAL(col)", false, "ACC(col)", false],
        "Para evitar SQL Injection no back-end com PDO você deve..." =>
            ["Usar prepared statements (prepare/execute)", true, "Concatenar strings com addslashes", false, "Escapar com htmlentities", false, "Validar só no front-end", false],
        "Qual comando cria uma chave estrangeira?" =>
            ["FOREIGN KEY ... REFERENCES ...", true, "REF KEY ...", false, "LINK KEY ...", false, "EXT KEY ...", false],
        "Qual cláusula define a ordenação crescente/decrescente?" =>
            ["ORDER BY col ASC|DESC", true, "SORT BY col ASC|DESC", false, "ARRANGE BY col", false, "ALIGN BY col", false],
        "Qual comando remove uma tabela inteira (estrutura e dados)?" =>
            ["DROP TABLE", true, "DELETE TABLE", false, "REMOVE TABLE", false, "TRUNCATE SCHEMA", false],
    ];
    seed_block($pdo, 'Back-end', 'SQL', $qs);
    echo "Seed Back-end/SQL base (10) criado.\n";
}

/* Dados / Python (10 base) */
$existsDataPy = $pdo->query("SELECT COUNT(*) FROM questions WHERE area='Dados' AND lang='Python'")->fetchColumn();
if ((int)$existsDataPy === 0) {
    $qs = [
        "Qual estrutura é ideal para representar uma sequência imutável?" =>
            ["tuple", true, "list", false, "set", false, "dict", false],
        "Qual função lê um arquivo texto inteiro como string?" =>
            ["open('arq.txt','r').read()", true, "read('arq.txt')", false, "file.read('arq.txt')", false, "get('arq.txt')", false],
        "Qual método divide uma string em lista por separador?" =>
            ["split()", true, "explode()", false, "divide()", false, "tokenize()", false],
        "Para iterar índice e valor ao mesmo tempo, usamos:" =>
            ["enumerate()", true, "index()", false, "iteritems()", false, "zipindex()", false],
        "Qual exceção indica erro de conversão de tipo/valor?" =>
            ["ValueError", true, "TypeError", false, "KeyError", false, "IndexError", false],
        "Como criar uma compreensão de lista com filtro (0..9 pares)?" =>
            ["[x for x in range(10) if x % 2 == 0]", true, "[x in range(10) where even]", false, "list(range(10)).filter(even)", false, "[x:even in range(10)]", false],
        "Qual método remove e retorna o último elemento da lista?" =>
            ["pop()", true, "poplast()", false, "remove()", false, "shift()", false],
        "Qual biblioteca é mais associada a arrays numéricos?" =>
            ["NumPy", true, "Requests", false, "Flask", false, "Pillow", false],
        "Qual função retorna o maior valor de um iterável?" =>
            ["max()", true, "largest()", false, "greatest()", false, "top()", false],
        "Qual método mescla duas listas (concatenar) de forma simples?" =>
            ["lista1 + lista2", true, "lista1.append(lista2)", false, "concat(lista1, lista2)", false, "merge(lista1, lista2)", false],
    ];
    seed_block($pdo, 'Dados', 'Python', $qs);
    echo "Seed Dados/Python base (10) criado.\n";
}

/* =========================
   EXPANSÃO (+10) → total 20
========================= */

/* Back-end / PHP (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='PHP'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Qual função filtra tags perigosas de HTML?" =>
            ["strip_tags()", true, "htmlspecialchars()", false, "filter_html()", false, "sanitize()", false],
        "Qual diretiva exibe erros de forma detalhada em dev?" =>
            ["ini_set('display_errors', 1)", true, "error_enable()", false, "show_errors()", false, "debug_on()", false],
        "Qual função inclui arquivo e gera erro fatal se não encontrar?" =>
            ["require", true, "include", false, "import", false, "use", false],
        "Qual superglobal contém cabeçalhos e info do servidor?" =>
            ["\$_SERVER", true, "\$_ENV", false, "\$_REQUEST", false, "\$_COOKIE", false],
        "Qual função gera tokens aleatórios criptograficamente fortes?" =>
            ["random_bytes()", true, "mt_rand()", false, "rand()", false, "uniqid()", false],
        "Como ativar exceções no PDO (modo de erro)?" =>
            ["\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)", true, "PDO::THROW()", false, "set_exception()", false, "ERRMODE=ON", false],
        "Qual função compara hashes de forma segura a timing attacks?" =>
            ["hash_equals()", true, "==", false, "strcmp()", false, "verify_hash()", false],
        "Como iniciar saída em buffer?" =>
            ["ob_start()", true, "buffer_start()", false, "output_begin()", false, "start_buffer()", false],
        "Qual função valida formato de e-mail?" =>
            ["filter_var(\$email, FILTER_VALIDATE_EMAIL)", true, "valid_email(\$email)", false, "email_is_valid(\$email)", false, "regex_email(\$email)", false],
        "Qual função destrói a sessão?" =>
            ["session_destroy()", true, "session_stop()", false, "end_session()", false, "destroy_session()", false],
    ];
    seed_block($pdo, 'Back-end', 'PHP', $qs);
    echo "Back-end/PHP aumentado para 20.\n";
}

/* Front-end / JavaScript (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Front-end' AND lang='JavaScript'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Qual método retorna uma NodeList com todos que combinam o seletor?" =>
            ["document.querySelectorAll()", true, "document.selectAll()", false, "document.getAll()", false, "document.nodes()", false],
        "Qual método converte número para string?" =>
            ["(123).toString()", true, "String.num(123)", false, "toStr(123)", false, "cast('123')", false],
        "Como impedir propagação do evento?" =>
            ["event.stopPropagation()", true, "event.cancel()", false, "event.stop()", false, "event.freeze()", false],
        "Qual método clona um array superficialmente?" =>
            ["array.slice()", true, "array.copy()", false, "array.clone()", false, "array.dup()", false],
        "O que retorna `Array.isArray([])`?" =>
            ["true", true, "false", false, "[]", false, "undefined", false],
        "Qual símbolo define template strings?" =>
            ["Crases (`)", true, "Aspas duplas", false, "Aspas simples", false, "Tanto faz", false],
        "Qual palavra-chave declara variável reatribuível com escopo de bloco?" =>
            ["let", true, "var", false, "const", false, "mutable", false],
        "Qual método mescla arrays gerando novo array?" =>
            ["[...a, ...b]", true, "a.merge(b)", false, "a.concatInPlace(b)", false, "join(a,b)", false],
        "Qual função agenda execução após X ms?" =>
            ["setTimeout()", true, "setInterval()", false, "delay()", false, "sleep()", false],
        "Qual valor de `typeof NaN`?" =>
            ["'number'", true, "'NaN'", false, "'undefined'", false, "'object'", false],
    ];
    seed_block($pdo, 'Front-end', 'JavaScript', $qs);
    echo "Front-end/JavaScript aumentado para 20.\n";
}

/* Back-end / Python (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='Python'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Qual método insere em posição específica da lista?" =>
            ["insert(idx, val)", true, "add(idx, val)", false, "pushAt(idx, val)", false, "appendAt(idx, val)", false],
        "Como criar um set vazio?" =>
            ["set()", true, "{}", false, "emptyset()", false, "()", false],
        "Qual função retorna um iterador de pares (chave, valor) do dict?" =>
            ["dict.items()", true, "dict.pairs()", false, "dict.iter()", false, "dict.values()", false],
        "Qual palavra-chave define bloco que sempre executa?" =>
            ["finally", true, "else", false, "ensure", false, "always", false],
        "Qual expressão cria gerador de 0 a 4?" =>
            ["(x for x in range(5))", true, "[x for x in range(5)]", false, "gen(range(5))", false, "yield range(5)", false],
        "Qual módulo padrão manipula JSON?" =>
            ["json", true, "pickle", false, "marshal", false, "csv", false],
        "Como checar tipo de uma variável?" =>
            ["isinstance(x, Tipo)", true, "typeOf(x) == Tipo", false, "typeof(x)==Tipo", false, "x.type()==Tipo", false],
        "Qual exceção para chave inexistente em dict?" =>
            ["KeyError", true, "IndexError", false, "LookupError", false, "TypeError", false],
        "Qual função retorna soma de iterável numérico?" =>
            ["sum()", true, "total()", false, "acc()", false, "plus()", false],
        "Qual modo abre arquivo para anexar no fim?" =>
            ["'a'", true, "'x'", false, "'w+'", false, "'r+'", false],
    ];
    seed_block($pdo, 'Back-end', 'Python', $qs);
    echo "Back-end/Python aumentado para 20.\n";
}

/* Back-end / SQL (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Back-end' AND lang='SQL'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Qual comando remove todas as linhas mantendo a estrutura?" =>
            ["TRUNCATE TABLE", true, "DELETE TABLE", false, "DROP ROWS", false, "CLEAR TABLE", false],
        "Qual cláusula retorna somente grupos que atendem condição de agregação?" =>
            ["HAVING", true, "WHERE", false, "GROUP BY", false, "FILTER", false],
        "Qual palavra ordena aleatoriamente em SQLite?" =>
            ["ORDER BY RANDOM()", true, "ORDER BY RAND()", false, "SORT RANDOM()", false, "RANDOM()", false],
        "Como limitar e pular linhas em SQLite?" =>
            ["LIMIT n OFFSET m", true, "TOP n SKIP m", false, "LIMIT m,n", false, "ROWLIMIT n,m", false],
        "Qual comando cria uma view?" =>
            ["CREATE VIEW", true, "MAKE VIEW", false, "NEW VIEW", false, "ADD VIEW", false],
        "Qual comando renomeia uma tabela?" =>
            ["ALTER TABLE old RENAME TO new", true, "RENAME TABLE old TO new", false, "CHANGE TABLE old new", false, "ALTER RENAME TABLE", false],
        "Qual operador retorna linhas de A que não casam com B?" =>
            ["LEFT JOIN ... WHERE b.col IS NULL", true, "ANTI JOIN", false, "NOTJOIN", false, "EXCLUDE JOIN", false],
        "Qual função retorna média?" =>
            ["AVG(col)", true, "MEAN(col)", false, "AVERAGE(col)", false, "MID(col)", false],
        "Qual comando cria índice único?" =>
            ["CREATE UNIQUE INDEX", true, "CREATE INDEX UNIQUE", false, "NEW UNIQUE KEY", false, "ADD UNIQUE", false],
        "Como proteger contra SQL Injection no back-end?" =>
            ["Prepared statements", true, "Concatenar strings com escapes", false, "Validar só no front", false, "Sanitizar com regex", false],
    ];
    seed_block($pdo, 'Back-end', 'SQL', $qs);
    echo "Back-end/SQL aumentado para 20.\n";
}

/* Dados / SQL (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Dados' AND lang='SQL'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Qual função retorna valor máximo?" =>
            ["MAX(col)", true, "TOP(col)", false, "HIGH(col)", false, "UP(col)", false],
        "Qual operador une resultados removendo duplicados (linhas iguais)?" =>
            ["UNION", true, "UNION ALL", false, "JOIN", false, "MERGE", false],
        "Qual operador une resultados mantendo duplicados?" =>
            ["UNION ALL", true, "UNION DUP", false, "JOIN ALL", false, "APPEND", false],
        "Qual comando cria tabela temporária (SQLite)?" =>
            ["CREATE TEMP TABLE", true, "CREATE GLOBAL TEMP", false, "CREATE TMP TABLE", false, "CREATE TEMPORARY", false],
        "Como contar distintos de uma coluna?" =>
            ["COUNT(DISTINCT col)", true, "COUNT UNIQUE col", false, "COUNT UNIQUE(col)", false, "DISTINCT COUNT(col)", false],
        "Qual comando adiciona restrição NOT NULL em coluna nova?" =>
            ["ALTER TABLE ... ADD COLUMN col TYPE NOT NULL", true, "MODIFY NOT NULL", false, "ADD CONSTRAINT NOT NULL", false, "SET NOT NULL", false],
        "Qual cláusula filtra após agregação?" =>
            ["HAVING", true, "WHERE", false, "FILTER", false, "GROUP HAVING", false],
        "Qual comando exclui view?" =>
            ["DROP VIEW", true, "DELETE VIEW", false, "REMOVE VIEW", false, "ERASE VIEW", false],
        "Como fazer paginação simples?" =>
            ["LIMIT ? OFFSET ?", true, "TOP ? SKIP ?", false, "LIMIT ?, ?", false, "ROWLIMIT ?, ?", false],
        "Qual tipo de join retorna todas as linhas de ambas as tabelas?" =>
            ["FULL OUTER JOIN (quando suportado)", true, "MIX JOIN", false, "TOTAL JOIN", false, "DUAL JOIN", false],
    ];
    seed_block($pdo, 'Dados', 'SQL', $qs);
    echo "Dados/SQL aumentado para 20.\n";
}

/* Dados / Python (+10) */
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE area='Dados' AND lang='Python'")->fetchColumn();
if ($cnt < 20) {
    $qs = [
        "Como ler JSON de string para objeto?" =>
            ["json.loads(s)", true, "json.read(s)", false, "json.parse(s)", false, "load.json(s)", false],
        "Como escrever objeto Python como JSON em arquivo?" =>
            ["json.dump(obj, f)", true, "json.write(obj, f)", false, "json.save(obj, f)", false, "json.dumps(obj, f)", false],
        "Qual função aplica função acumuladora a um iterável?" =>
            ["functools.reduce()", true, "accumulate()", false, "apply()", false, "fold()", false],
        "Como ordenar lista por chave customizada?" =>
            ["sorted(seq, key=func)", true, "sort(seq, by=func)", false, "order(seq, key=func)", false, "seq.sortBy(func)", false],
        "Qual método retorna novo iterável filtrado por predicado?" =>
            ["filter(func, iter)", true, "where(func, iter)", false, "select(func, iter)", false, "mask(func, iter)", false],
        "Qual expressão faz unpacking de dicionário em função?" =>
            ["f(**kwargs)", true, "f(*(kwargs))", false, "f(&kwargs)", false, "f(dict...)", false],
        "Como contar itens em uma lista?" =>
            ["len(lista)", true, "count(lista)", false, "size(lista)", false, "length(lista)", false],
        "Qual biblioteca é comumente usada para dataframes?" =>
            ["pandas", true, "requests", false, "django", false, "pytest", false],
        "Qual método concatena listas sem alterar originais?" =>
            ["lista1 + lista2", true, "lista1.extend(lista2) (altera lista1)", false, "concat(lista1, lista2)", false, "merge(lista1, lista2)", false],
        "Qual módulo manipula CSV no Python padrão?" =>
            ["csv", true, "xlsx", false, "excel", false, "tables", false],
    ];
    seed_block($pdo, 'Dados', 'Python', $qs);
    echo "Dados/Python aumentado para 20.\n";
}

echo "Seed concluído.\n";