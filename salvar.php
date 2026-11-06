<?php
/**
 * salvar.php — Grava os dados do portal no arquivo dados.json
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ===== MESMA SENHA DOS OUTROS =====
const SENHA = 'MIRASSOL_TI_2026';

$PASTA = __DIR__;
$ARQ_DADOS  = $PASTA . '/dados.json';
$ARQ_BACKUP = $PASTA . '/dados.backup.json';
$PASTA_HIST = $PASTA . '/backups';

// ===== AUTENTICAÇÃO =====
$senhaRecebida = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
if ($senhaRecebida !== SENHA) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'erro' => 'Não autorizado']);
  exit;
}

// ===== MÉTODO =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'erro' => 'Use POST']);
  exit;
}

// ===== LÊ O CORPO =====
$corpo = file_get_contents('php://input');
if ($corpo === false || strlen($corpo) === 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'erro' => 'Corpo vazio']);
  exit;
}

// Valida JSON
$dados = json_decode($corpo, true);
if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'erro' => 'JSON inválido: ' . json_last_error_msg()]);
  exit;
}

// Valida estrutura mínima
if (!is_array($dados) || !isset($dados['systems']) || !isset($dados['categories'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'erro' => 'Estrutura inválida']);
  exit;
}

// ===== GARANTE PASTAS =====
if (!is_dir($PASTA_HIST)) {
  @mkdir($PASTA_HIST, 0775, true);
}

// ===== GRAVA COM LOCK =====
$fp = fopen($ARQ_DADOS, 'c+');
if ($fp === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Não foi possível abrir o arquivo']);
  exit;
}

if (!flock($fp, LOCK_EX)) {
  fclose($fp);
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Falha ao travar o arquivo']);
  exit;
}

// Guarda o conteúdo atual como backup ANTES de sobrescrever
$conteudoAntigo = '';
if (filesize($ARQ_DADOS) > 0) {
  rewind($fp);
  $conteudoAntigo = stream_get_contents($fp);
}

// Serializa
$json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
  flock($fp, LOCK_UN);
  fclose($fp);
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Falha ao serializar JSON']);
  exit;
}

// Grava
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, $json);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Atualiza backup "anterior"
if ($conteudoAntigo !== '' && $conteudoAntigo !== $json) {
  @file_put_contents($ARQ_BACKUP, $conteudoAntigo);
}

// Snapshot diário (últimos 30 dias)
$hoje = date('Y-m-d');
$snapHoje = $PASTA_HIST . '/dados-' . $hoje . '.json';
if (!file_exists($snapHoje)) {
  @file_put_contents($snapHoje, $json);
  $arquivos = glob($PASTA_HIST . '/dados-*.json');
  if ($arquivos) {
    $limite = strtotime('-30 days');
    foreach ($arquivos as $arq) {
      if (preg_match('/dados-(\d{4}-\d{2}-\d{2})\.json$/', $arq, $m)) {
        if (strtotime($m[1]) < $limite) @unlink($arq);
      }
    }
  }
}

echo json_encode([
  'ok' => true,
  'bytes' => strlen($json),
  'salvoEm' => date('Y-m-d H:i:s'),
]);