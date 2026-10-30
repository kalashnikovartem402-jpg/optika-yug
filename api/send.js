module.exports = async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET,OPTIONS,PATCH,DELETE,POST,PUT');
  res.setHeader(
    'Access-Control-Allow-Headers',
    'X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version'
  );

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ status: 'error', message: 'Method not allowed' });
  }

  try {
    let body = req.body || {};
    if (typeof body === 'string') {
      try {
        body = JSON.parse(body);
      } catch (e) {
        body = Object.fromEntries(new URLSearchParams(body));
      }
    }

    // Honeypot
    if (body.antispam || body.website_hp) {
      return res.status(200).json({ status: 'success', message: 'Заявка принята' });
    }

    const name = body.name || (body['field_name'] && body['field_name'][0] && body['field_name'][0].value) || '—';
    const phone = body.phone || (body['field_contact'] && body['field_contact'][0] && body['field_contact'][0].value) || (body['field_phone'] && body['field_phone'][0] && body['field_phone'][0].value) || '';
    const details = body.details || body.message || (body['field_details'] && body['field_details'][0] && body['field_details'][0].value) || '—';
    const formTitle = body.form_id === 'contact_message_callback_form' ? 'Заказ обратного звонка' : 'Заявка с сайта';

    const digitsCount = (phone.match(/\d/g) || []).length;
    if (digitsCount < 7) {
      return res.status(400).json({ status: 'error', message: 'Пожалуйста, укажите корректный номер телефона.' });
    }

    const dateTime = new Date().toLocaleString('ru-RU', { timeZone: 'Europe/Moscow' });
    const clientIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '—';

    console.log(`[Lead] ${dateTime} | ${formTitle} | ${name} | ${phone} | ${details}`);

    const tgToken = process.env.TELEGRAM_BOT_TOKEN;
    const tgChatId = process.env.TELEGRAM_CHAT_ID;
    if (tgToken && tgChatId) {
      try {
        const text = `*${formTitle}*\n\n` +
          `*Имя:* ${name}\n` +
          `*Телефон:* ${phone}\n` +
          `*Сообщение:* ${details}\n` +
          `*Дата:* ${dateTime}`;

        await fetch(`https://api.telegram.org/bot${tgToken}/sendMessage`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ chat_id: tgChatId, text: text, parse_mode: 'Markdown' })
        });
      } catch (e) {
        console.error('Telegram send error:', e);
      }
    }

    const resendApiKey = process.env.RESEND_API_KEY;
    if (resendApiKey) {
      try {
        await fetch('https://api.resend.com/emails', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${resendApiKey}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            from: 'Оптика Юг <onboarding@resend.dev>',
            to: ['Opticasouth@yandex.ru'],
            subject: `${formTitle} [${phone}]`,
            html: `<p><b>Имя:</b> ${name}</p><p><b>Телефон:</b> ${phone}</p><p><b>Сообщение:</b> ${details}</p><p><small>${dateTime} | ${clientIp}</small></p>`
          })
        });
      } catch (e) {
        console.error('Resend error:', e);
      }
    }

    return res.status(200).json({
      status: 'success',
      message: 'Спасибо! Ваша заявка успешно отправлена.'
    });

  } catch (error) {
    console.error('Server error:', error);
    return res.status(500).json({ status: 'error', message: 'Internal server error' });
  }
};
