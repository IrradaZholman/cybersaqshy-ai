<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["user_id"])) {
  echo json_encode([
    "success" => false,
    "message" => "Жүйеге кіріңіз"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$conn = new mysqli(
  "sql200.infinityfree.com",
  "if0_41850510",
  "Uhrhq1SxdwVi",
  "if0_41850510_fishing"
);

if ($conn->connect_error) {
  echo json_encode([
    "success" => false,
    "message" => "Database error: " . $conn->connect_error
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$OPENAI_API_KEY = getenv("OPENAI_API_KEY");

if (!$OPENAI_API_KEY) {
  echo json_encode([
    "success" => false,
    "message" => "OpenAI API key Render Environment Variables ішінде жазылмаған"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode([
    "success" => false,
    "message" => "JSON мәлімет дұрыс емес"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$message = trim($data["message"] ?? "");

if ($message === "") {
  echo json_encode([
    "success" => false,
    "message" => "Сұрақ бос"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$prompt = "
Сен CYBERSAQSHY атты киберқауіпсіздік AI-ассистентісің.

Ережелер:
- Әрқашан қазақ тілінде жауап бер.
- Жауап қысқа, түсінікті және оқушыға жеңіл болсын.
- Киберқауіпсіздік тақырыбында нақты кеңес бер.
- Егер сұрақ фишинг, SMS, жалған сайт, пароль, QR код, вирус немесе алаяқтық туралы болса, қауіпсіздік ережелерін түсіндір.
- Қажет болса мысал келтір.
- Өте ұзын жауап жазба.
- Markdown қолданба.
- Артық символдар мен эмодзилерді көп қолданба.

Пайдаланушы сұрағы:
" . $message;

$payload = [
  "model" => "gpt-4.1-mini",
  "input" => $prompt
];

$ch = curl_init("https://api.openai.com/v1/responses");

curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_TIMEOUT => 60,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Authorization: Bearer " . $OPENAI_API_KEY
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($error) {
  echo json_encode([
    "success" => false,
    "message" => "CURL қатесі: " . $error
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
  echo json_encode([
    "success" => false,
    "message" => "OpenAI API қатесі: HTTP " . $httpCode,
    "raw" => $response
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$res = json_decode($response, true);

if (!is_array($res)) {
  echo json_encode([
    "success" => false,
    "message" => "OpenAI жауабы JSON емес",
    "raw" => $response
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$answer = $res["output"][0]["content"][0]["text"] ?? "";

if ($answer === "") {
  echo json_encode([
    "success" => false,
    "message" => "AI бос жауап қайтарды",
    "raw" => $response
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$user_id = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("
  INSERT INTO ai_chat_history (user_id, question, answer)
  VALUES (?, ?, ?)
");

if (!$stmt) {
  echo json_encode([
    "success" => false,
    "message" => "SQL prepare қатесі: " . $conn->error
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$stmt->bind_param("iss", $user_id, $message, $answer);

if (!$stmt->execute()) {
  echo json_encode([
    "success" => false,
    "message" => "Тарихқа сақталмады: " . $stmt->error
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode([
  "success" => true,
  "answer" => $answer,
  "time" => date("H:i")
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
