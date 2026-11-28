/**
 * The product form is built from these lists.
 * Add a field: add a line here AND its name to $TEXT_FIELDS (or $FLAG_FIELDS) in server/admin/courses.php.
 *
 * type:    text | number | textarea | select (options come from the server: optionsKey)
 * showIf:  (values) => true/false   show the field only when this is true
 * fill:    { label, text }          a small button that puts ready-made text in the field
 */

import { DEFAULT_MICRO_TNC, DEFAULT_TNC } from '../../../lib/defaultTerms';

const isOff = (v, f) => !(v[f] == 1 || v[f] === true);
const isOn = (v, f) => !isOff(v, f);

// The categories a product can be in (same as the website's store). Add one here to get it in the form.
// 'report-writing' was an older name of Projects & Reports; the website already treats it as projects.
export const CATEGORIES = [
  { id: 'notes', label: 'Notes', icon: '📝' },
  { id: 'ebooks', label: 'E-Books', icon: '📚' },
  { id: 'important', label: 'Important Questions', icon: '⭐' },
  { id: 'projects', label: 'Projects & Reports', icon: '🛠️' },
  { id: 'resume', label: 'Resume Templates', icon: '📄' },
];
export const normalizeCategory = (c) => {
  const v = String(c || '').trim().toLowerCase();
  if (v === 'report-writing') return 'projects';
  return CATEGORIES.some((x) => x.id === v) ? v : 'notes';
};

export const FIELDS = [
  { name: 'title', label: 'Title', type: 'text', full: true, required: true, hint: 'e.g. Complete Web Development' },
  { name: 'category', label: 'Category', type: 'choice', full: true, required: true, hint: 'Where this card is shown in the store.' },
  { name: 'badge', label: 'Badge (optional)', type: 'text', hint: 'e.g. NEW or Bestseller' },
  { name: 'price', label: 'Selling price (₹)', type: 'number', required: true, hint: 'For a Resume Template this is the amount the student pays when downloading the PDF (customizing is free). Write 0 to make the template FREE.' },
  { name: 'old_price', label: 'Original price (₹, crossed out)', type: 'number' },
  { name: 'desc1', label: 'Bullet point 1', type: 'text', full: true, hint: 'e.g. 🚧 Exam Oriented Notes' },
  { name: 'desc2', label: 'Bullet point 2', type: 'text', full: true, hint: 'e.g. 📄 Total Pages: 25' },
  {
    name: 'website_link',
    label: 'Project website link (only for Projects)',
    type: 'text',
    full: true,
    showIf: (v) => v.category === 'projects' || v.category === 'report-writing',
    hint: 'e.g. https://project-demo.com',
  },
  { name: 'link', label: 'Direct payment link (optional)', type: 'text', full: true },
  {
    name: 'demo_link',
    label: 'Demo link (for the Preview button only)',
    type: 'text',
    full: true,
    hint: 'For a Resume product write: template:modern (or classic / compact). A new template made as a file in src/templates/resume/Name.jsx is written as template:name',
  },
  { name: 'btn_text', label: 'Button text', type: 'text', required: true },
  { name: 'btn_type', label: 'Button style', type: 'select', optionsKey: 'btnTypes' },
  {
    name: 'download_msg',
    label: 'Message or time for the student (optional)',
    type: 'textarea',
    full: true,
    placeholder: 'e.g. Download opens on Sunday 6 PM',
    showIf: (v) => isOff(v, 'allow_download'),
    hint: 'Type your own message or time. The student sees exactly this when the download is locked (not allowed, or waiting for the time above). Empty = the automatic text.',
  },
  {
    name: 'micro_download_msg',
    label: 'Message or time for the student (optional)',
    type: 'textarea',
    full: true,
    rows: 2,
    placeholder: 'e.g. Micro sheet download opens on Sunday 6 PM',
    showIf: (v) => isOff(v, 'micro_allow_download'),
    hint: 'Type your own message or time. The student sees exactly this when the micro download is locked. Empty = the automatic text.',
  },
  { name: 'micro_price', label: 'Micro format price (₹)', type: 'number', hint: 'What the buyer pays for the micro format, on top of this product.' },
  {
    name: 'micro_tnc_text',
    label: 'Terms for the micro format',
    type: 'textarea',
    full: true,
    rows: 7,
    showIf: (v) => v.micro_show_tnc == 1 || v.micro_show_tnc === true,
    fill: { label: 'Load default sample', text: DEFAULT_MICRO_TNC },
    hint: 'One point per line, like “1. Title: rest of the text”. Empty = the default sample is used.',
  },
  {
    name: 'tnc_text',
    label: 'Custom terms & conditions',
    type: 'textarea',
    full: true,
    rows: 9,
    showIf: (v) => isOn(v, 'show_tnc'),
    fill: { label: 'Load default sample', text: DEFAULT_TNC },
    hint: 'One point per line, like “1. Title: rest of the text”. Empty = the default sample is used.',
  },
];

// The form is drawn in these blocks, in this order. `files: true` shows the picture + PDF pickers.
export const SECTIONS = [
  { title: 'Basic details', hint: 'What the product is called and where it appears.', fields: ['title', 'category', 'badge'] },
  { title: 'Price', hint: 'Shown on the store card.', fields: ['price', 'old_price'] },
  { title: 'Card text', hint: 'The two short lines under the title.', fields: ['desc1', 'desc2'] },
  { title: 'Picture & PDF', hint: 'Upload the card picture and the file students read.', files: true },
  { title: 'Questions text', hint: 'Write or paste the questions here, instead of a PDF. The buyer reads this text on the website.', qa: true, showIf: (v) => v.category === 'important' },
  {
    title: 'Micro format (A4 boxes)',
    hint: 'A paid extra: the same text in small A4 boxes the buyer can download or print.',
    micro: true,
    flags: ['micro_enabled'],
    showIf: (v) => v.category === 'important',
  },
  { title: 'Links & button', hint: 'Where the buy / preview button goes and how it looks.', fields: ['website_link', 'link', 'demo_link', 'btn_text', 'btn_type'] },
  { title: 'Download', hint: 'When can the buyer download the PDF?', download: true, fields: ['download_msg'] },
  { title: 'Behaviour', hint: 'Switch things on or off for this product.', flags: ['app_only', 'show_preview', 'grants_pyq', 'show_report_btn'] },
  { title: 'Terms & conditions', hint: 'Ask the buyer to accept terms before paying.', flags: ['show_tnc'], fields: ['tnc_text'] },
];

// on/off switches (inside the form and, when inList is true, as quick switches in the product list)
export const FLAGS = [
  // micro format of an Important Questions product (shown in its own section, not in Behaviour)
  { name: 'micro_enabled', label: 'Sell the micro format', hint: 'ON = buyers see a “Micro format” button above the questions and can pay for it.' },
  { name: 'micro_allow_download', label: 'Micro download allowed', hint: '' },
  { name: 'micro_show_tnc', label: 'Ask for terms before paying for the micro format', hint: 'ON = a separate terms box is shown when the buyer pays for the micro format.' },
  { name: 'allow_download', label: 'Show Download button', hint: 'ON = download works. OFF = a message box opens for the student.', inList: true, listLabel: 'Download' },
  { name: 'app_only', label: 'App only (buy & access)', hint: 'ON = the website only lists it; buying and opening happen in the Android app.', inList: true, listLabel: 'App only' },
  { name: 'show_preview', label: 'Show Preview card', hint: 'ON = the store card shows a Preview (only the 1st page of the PDF).', inList: true, listLabel: 'Preview' },
  { name: 'grants_pyq', label: 'Gives D2D PYQ access', hint: 'ON = buyers of this product also get D2D PYQ access.', inList: true, listLabel: 'D2D PYQ' },
  { name: 'show_tnc', label: 'Show T&C checkbox', hint: 'ON = the buyer must accept the terms before paying.' },
  { name: 'show_report_btn', label: 'Show Report button', hint: 'ON = students can report a problem.' },
];

export const emptyCourse = {
  title: '',
  category: 'notes',
  badge: '',
  price: '',
  old_price: '',
  desc1: '',
  desc2: '',
  link: '',
  demo_link: '',
  website_link: '',
  btn_text: 'Buy Now',
  btn_type: 'normal',
  tnc_text: '',
  download_msg: '',
  image: '',
  pdf_file: '',
  download_at: '',
  qa_text: '',
  micro_price: '',
  micro_download_at: '',
  micro_download_msg: '',
  micro_tnc_text: '',
  micro_allow_download: 1,
};
