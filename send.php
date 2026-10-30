<?php
header('Content-Type: application/json; charset=UTF-8');

$to_emails = ['Opticasouth@yandex.ru'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw_input = file_get_contents('php://input');
if (!empty($raw_input)) {
    $json_data = json_decode($raw_input, true);
    if (is_array($json_data)) {
        $_POST = array_merge($_POST, $json_data);
    }
}

// Honeypot
if (!empty($_POST['website_hp']) || !empty($_POST['antispam'])) {
    echo json_encode(['status' => 'success', 'message' => 'Заявка принята'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = '';
if (isset($_POST['field_name'][0]['value'])) {
    $name = trim($_POST['field_name'][0]['value']);
} elseif (isset($_POST['name'])) {
    $name = trim($_POST['name']);
}

$phone = '';
if (isset($_POST['field_contact'][0]['value'])) {
    $phone = trim($_POST['field_contact'][0]['value']);
} elseif (isset($_POST['field_phone'][0]['value'])) {
    $phone = trim($_POST['field_phone'][0]['value']);
} elseif (isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
}

$details = '';
if (isset($_POST['field_details'][0]['value'])) {
    $details = trim($_POST['field_details'][0]['value']);
} elseif (isset($_POST['details'])) {
    $details = trim($_POST['details']);
} elseif (isset($_POST['message'])) {
    $details = trim($_POST['message']);
}

$form_id = isset($_POST['form_id']) ? trim($_POST['form_id']) : 'Заявка с сайта';
$form_title = ($form_id === 'contact_message_callback_form') ? 'Заказ обратного звонка' : 'Заявка на консультацию / заказ';

$name = htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(strip_tags($phone), ENT_QUOTES, 'UTF-8');
$details = htmlspecialchars(strip_tags($details), ENT_QUOTES, 'UTF-8');

$digits_count = strlen(preg_replace('/\D/', '', $phone));
if ($digits_count < 7) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Пожалуйста, укажите корректный номер телефона.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subject = "Новая заявка с сайта: {$form_title} [{$phone}]";
$date_time = date('d.m.Y H:i:s');
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'Не определен';

$email_content = "
<!DOCTYPE html>
<html lang='ru'>
<head>
  <meta charset='utf-8'>
  <title>{$form_title}</title>
</head>
<body style='font-family: Arial, sans-serif; background-color: #f7f9fa; margin: 0; padding: 20px; color: #1e293b;'>
  <table width='100%' border='0' cellspacing='0' cellpadding='0'>
    <tr>
      <td align='center'>
        <table width='600' border='0' cellspacing='0' cellpadding='0' style='background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08);'>
          <tr>
            <td style='background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px; text-align: center; color: #ffffff;'>
              <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>Салон оптики «Оптика Юг»</h1>
              <p style='margin: 8px 0 0; font-size: 15px; opacity: 0.9;'>{$form_title}</p>
            </td>
          </tr>
          <tr>
            <td style='padding: 30px 40px;'>
              <table width='100%' border='0' cellspacing='0' cellpadding='10' style='font-size: 15px;'>";

if (!empty($name)) {
    $email_content .= "
                <tr>
                  <td width='35%' style='color: #64748b; font-weight: bold; border-bottom: 1px solid #f1f5f9;'>Имя:</td>
                  <td width='65%' style='color: #0f172a; font-weight: bold; border-bottom: 1px solid #f1f5f9;'>{$name}</td>
                </tr>";
}

$email_content .= "
                <tr>
                  <td style='color: #64748b; font-weight: bold; border-bottom: 1px solid #f1f5f9;'>Телефон:</td>
                  <td style='color: #0284c7; font-size: 18px; font-weight: bold; border-bottom: 1px solid #f1f5f9;'><a href='tel:{$phone}' style='color: #0284c7; text-decoration: none;'>{$phone}</a></td>
                </tr>";

if (!empty($details)) {
    $email_content .= "
                <tr>
                  <td style='color: #64748b; font-weight: bold; border-bottom: 1px solid #f1f5f9; vertical-align: top;'>Сообщение:</td>
                  <td style='color: #0f172a; border-bottom: 1px solid #f1f5f9;'>{$details}</td>
                </tr>";
}

$email_content .= "
                <tr>
                  <td style='color: #64748b; border-bottom: 1px solid #f1f5f9;'>Дата:</td>
                  <td style='color: #334155; border-bottom: 1px solid #f1f5f9;'>{$date_time}</td>
                </tr>
                <tr>
                  <td style='color: #64748b;'>IP:</td>
                  <td style='color: #334155;'>{$client_ip}</td>
                </tr>
              </table>
              <div style='margin-top: 30px; text-align: center;'>
                <a href='tel:{$phone}' style='background: #0284c7; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 50px; font-weight: bold; display: inline-block;'>Позвонить</a>
              </div>
            </td>
          </tr>
          <tr>
            <td style='background-color: #f8fafc; padding: 15px 30px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;'>
              г. Сочи, ул. Курортный пр-кт, д. 50 | +7 (988) 403-64-42
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";

$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: Оптика Юг <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'optikayug.ru') . '>',
    'Reply-To: ' . (!empty($name) ? "{$name} " : '') . '<' . $phone . '>',
    'X-Mailer: PHP/' . phpversion()
];

$headers_str = implode("\r\n", $headers);

$mail_sent = false;
foreach ($to_emails as $recipient) {
    if (@mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', $email_content, $headers_str)) {
        $mail_sent = true;
    }
}

$log_entry = sprintf(
    "[%s] IP: %s | Форма: %s | Имя: %s | Тел: %s | Текст: %s | Mail: %s\n",
    $date_time,
    $client_ip,
    $form_title,
    $name ?: '—',
    $phone,
    str_replace(["\r", "\n"], ' ', $details ?: '—'),
    $mail_sent ? 'OK' : 'FAIL/LOCAL'
);
@file_put_contents(__DIR__ . '/leads.log', $log_entry, FILE_APPEND | LOCK_EX);

echo json_encode([
    'status' => 'success',
    'message' => 'Спасибо! Ваша заявка успешно отправлена.'
], JSON_UNESCAPED_UNICODE);
