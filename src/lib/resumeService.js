/**
 * "Get my resume made by the admin" (the WhatsApp button under the resume editor).
 * Change the number or the extra charge here.
 */
export const WHATSAPP_NUMBER = '918709911016'; // same number as the site's footer (country code + number, digits only)
export const ADMIN_MADE_EXTRA = 49; // Rs added on top of the template price when the admin makes the resume

const one = (v, max = 400) => String(v || '').replace(/\s+/g, ' ').trim().slice(0, max);

/** The message that opens in WhatsApp (only after the template is paid): the template, the amounts and everything the student filled in. */
export function buildResumeMessage(product, data) {
  const L = [];
  L.push('*Resume banwana hai (Admin se)*');
  L.push(`Template: ${product.title} (#${product.id})`);
  L.push(Number(product.price) === 0 ? 'Template: free' : `Template payment: Rs ${product.price} (paid online)`);
  L.push(`Admin charge: Rs ${ADMIN_MADE_EXTRA} (the rest is to be paid to the admin)`);
  L.push('');
  L.push(`Name: ${one(data.name, 80)}`);
  if (data.title) L.push(`Job title / Degree: ${one(data.title, 120)}`);
  if (data.email) L.push(`Email: ${one(data.email, 80)}`);
  if (data.phone) L.push(`Phone: ${one(data.phone, 30)}`);
  if (data.location) L.push(`Location: ${one(data.location, 80)}`);
  if (data.link) L.push(`Link: ${one(data.link, 120)}`);
  if (data.summary) L.push('', `*Summary*`, one(data.summary, 500));
  if (data.skills?.length) L.push('', '*Skills*', data.skills.map((s) => one(s, 40)).join(', '));
  if (data.experience?.length) {
    L.push('', '*Experience / Internship*');
    data.experience.forEach((e, i) => {
      L.push(`${i + 1}. ${one(e.role, 80)} - ${one(e.company, 80)} (${one(e.period, 40)})`);
      String(e.points || '')
        .split('\n')
        .filter((t) => t.trim())
        .slice(0, 6)
        .forEach((t) => L.push(`   - ${one(t, 150)}`));
    });
  }
  if (data.education?.length) {
    L.push('', '*Education*');
    data.education.forEach((e, i) => L.push(`${i + 1}. ${one(e.degree, 100)} - ${one(e.school, 100)} (${one(e.period, 30)})${e.score ? ` ${one(e.score, 30)}` : ''}`));
  }
  if (data.projects?.length) {
    L.push('', '*Projects*');
    data.projects.forEach((p, i) => L.push(`${i + 1}. ${one(p.name, 80)}: ${one(p.desc, 200)}`));
  }
  let text = L.join('\n');
  if (text.length > 3500) text = text.slice(0, 3490) + '...'; // WhatsApp links have a length limit
  return text;
}

export const whatsappLink = (text) => `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(text)}`;
