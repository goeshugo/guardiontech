<?php
// app/mail.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function mailer_method(){ return strtoupper(setting('email.method','MAIL')); } // MAIL | SMTP
function mailer_from_email(){ return setting('email.from_email','no-reply@seu-dominio.com.br'); }
function mailer_from_name(){ return setting('email.from_name', APP_NAME ?? 'Sistema'); }

function send_email(string $to, string $subject, string $html, string $textAlt=''){
  $method = mailer_method();
  if ($method === 'SMTP' && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    return send_email_smtp($to,$subject,$html,$textAlt);
  }
  return send_email_mail($to,$subject,$html,$textAlt);
}

/* === via mail() (sem SMTP) === */
function send_email_mail(string $to, string $subject, string $html, string $textAlt=''){
  $from = mailer_from_email();
  $name = mailer_from_name();
  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=UTF-8';
  $headers[] = 'From: '.encode_name_email($name,$from);
  $headers[] = 'Reply-To: '.encode_name_email($name,$from);
  $headers[] = 'X-Mailer: PHP/'.phpversion();
  $ok = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, implode("\r\n",$headers), "-f$from");
  if (!$ok) throw new Exception('Falha ao enviar e-mail (mail()).');
  return true;
}

/* === via PHPMailer (SMTP) — opcional === */
function send_email_smtp(string $to, string $subject, string $html, string $textAlt=''){
  $host = setting('smtp.host','');
  $port = (int)setting('smtp.port',587);
  $user = setting('smtp.user','');
  $pass = setting('smtp.pass','');
  $secure = setting('smtp.secure','tls'); // tls|ssl|none

  $from = mailer_from_email();
  $name = mailer_from_name();

  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = $host;
  $mail->Port = $port ?: 587;
  if ($secure === 'ssl') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
  elseif ($secure === 'tls') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->SMTPAuth = ($user !== '' || $pass !== '');
  $mail->Username = $user;
  $mail->Password = $pass;
  $mail->CharSet = 'UTF-8';
  $mail->setFrom($from, $name);
  $mail->addAddress($to);
  $mail->Subject = $subject;
  $mail->isHTML(true);
  $mail->Body = $html;
  $mail->AltBody = ($textAlt !== '') ? $textAlt : strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html));
  $mail->send();
  return true;
}

function encode_name_email($name,$email){
  $name = trim((string)$name) !== '' ? '=?UTF-8?B?'.base64_encode($name).'?=' : $email;
  return $name.' <'.$email.'>';
}
