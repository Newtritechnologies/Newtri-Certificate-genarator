// ===== EmailJS settings (loaded BEFORE script.js) =====
// Get these three values from https://dashboard.emailjs.com
//   Public Key  -> Account > General (or API keys)
//   Service ID  -> Email Services (you already have it)
//   Template ID -> Email Templates
const EMAILJS_PUBLIC_KEY  = 'GGY-VSox7t9QJOy-k';
const EMAILJS_SERVICE_ID  = 'service_jxbxnpg';
const EMAILJS_TEMPLATE_ID = 'template_uwp1xya';

if (window.emailjs) { emailjs.init({ publicKey: EMAILJS_PUBLIC_KEY }); }
