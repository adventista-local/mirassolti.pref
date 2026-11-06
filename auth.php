<?php
header('Content-Type: application/json; charset=utf-8');
const SENHA = 'MIRASSOL_TI_2026';
$senhaRecebida = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
if ($senhaRecebida === SENHA) {
  echo json_encode(['ok' => true]);
} else {
  http_response_code(401);
  echo json_encode(['ok' => false, 'erro' => 'Senha incorreta']);
}