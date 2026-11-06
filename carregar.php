<?php
/**
 * carregar.php — Devolve os dados do portal (dados.json)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ===== MESMA SENHA DO auth.php =====
const SENHA = 'MIRASSOL_TI_2026';

$ARQ_DADOS = __DIR__ . '/dados.json';

// ===== AUTENTICAÇÃO =====
$senhaRecebida = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
if ($senhaRecebida !== SENHA) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'erro' => 'Não autorizado']);
  exit;
}

// ===== PRIMEIRA VEZ? =====
if (!file_exists($ARQ_DADOS)) {
  echo json_encode([
    'systems' => [],
    'categories' => ['Sistemas institucionais','Infraestrutura e redes','Suporte técnico','Documentação e gestão','Ferramentas online','Outros'],
    'contratos' => [],
    'portarias' => [],
    'notas' => [],
    'catNotas' => ['SIA','Contrato','Portaria','Fiscalização','Reunião','Prazo processual','RH','Compras','TI','Urgente','Outros'],
    'backupVersion' => 6,
    'novo' => true,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== LÊ O ARQUIVO =====
$fp = fopen($ARQ_DADOS, 'r');
if ($fp === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Não foi possível ler o arquivo']);
  exit;
}

flock($fp, LOCK_SH);
$conteudo = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

if ($conteudo === false || strlen($conteudo) === 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Arquivo vazio']);
  exit;
}

// Valida JSON antes de devolver
$teste = json_decode($conteudo, true);
if ($teste === null && json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'Arquivo corrompido: ' . json_last_error_msg()]);
  exit;
}

// Devolve direto
echo $conteudo;