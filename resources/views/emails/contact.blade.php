<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Nuovo contatto</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Nuovo messaggio dal sito danielpetrica.com</h2>

    <p><strong>Nome:</strong> {{ $senderName }}</p>
    <p><strong>Email:</strong> <a href="mailto:{{ $senderEmail }}">{{ $senderEmail }}</a></p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">

    <h3>Messaggio:</h3>
    <p style="white-space: pre-wrap;">{{ $userMessage }}</p>
</body>
</html>
