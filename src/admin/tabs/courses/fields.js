/**
 * The product form is built from these lists.
 * Add a field: add a line here AND its name to $TEXT_FIELDS (or $FLAG_FIELDS) in server/admin/courses.php.
 *
 * type:    text | number | textarea | select (options come from the server: optionsKey)
 * showIf:  (values) => true/false   show the field only when this is true
 * fill:    { label, text }          a small button that puts ready-made text in the field
 */

// Same sample as the website: used on every product that has no terms text of its own.
export const DEFAULT_TNC = `1. Non-Refundable: All sales are final. No refunds will be provided once the course access is granted.
2. No Sharing: Sharing your account details with others is strictly prohibited and will lead to an instant permanent ban without refund.
3. Personal Use: The study material is for your personal use only. Distribution or piracy will invite legal actions.
4. Download Policy: You can download the content only one day before the exam at 6:00 PM when the download button unlocks.
5. Payment Instruction: After successful payment, please wait for 10 seconds on the same page. Then check your profile for access to the purchased content.
6. Important Notice: This content is based on analysis and predictions; exact exam questions are not guaranteed.
7. Agreement: By proceeding, you agree to PREMium Mind's standard terms of service.`;

const isOff = (v, f) => !(v[f] == 1 || v[f] === true);
const isOn = (v, f) => !isOff(v, f);

export const FIELDS = [
  { name: 'title', label: 'Title', type: 'text', full: true, required: true, hint: 'e.g. Complete Web Development' },
  { name: 'category', label: 'Category', type: 'select', optionsKey: 'categories', required: true },
  { name: 'badge', label: 'Badge (optional)', type: 'text', hint: 'e.g. NEW or Bestseller' },
  { name: 'price', label: 'Selling price (₹)', type: 'number', required: true },
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
    hint: 'For a Resume product write: template:modern  (or template:classic / template:compact)',
  },
  { name: 'btn_text', label: 'Button text', type: 'text', required: true },
  { name: 'btn_type', label: 'Button style', type: 'select', optionsKey: 'btnTypes' },
  {
    name: 'download_msg',
    label: 'Download locked message',
    type: 'textarea',
    full: true,
    showIf: (v) => isOff(v, 'allow_download'),
    hint: 'Download is OFF: the student sees this when they press Download. Empty = default text.',
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
  { title: 'Links & button', hint: 'Where the buy / preview button goes and how it looks.', fields: ['website_link', 'link', 'demo_link', 'btn_text', 'btn_type'] },
  { title: 'Behaviour', hint: 'Switch things on or off for this product.', flags: ['allow_download', 'app_only', 'show_preview', 'grants_pyq', 'show_report_btn'], fields: ['download_msg'] },
  { title: 'Terms & conditions', hint: 'Ask the buyer to accept terms before paying.', flags: ['show_tnc'], fields: ['tnc_text'] },
];

// on/off switches (inside the form and, when inList is true, as quick switches in the product list)
export const FLAGS = [
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
};
