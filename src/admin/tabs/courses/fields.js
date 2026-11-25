/**
 * The product form is built from these lists.
 * Add a field: add a line here AND its name to $TEXT_FIELDS (or $FLAG_FIELDS) in server/admin/courses.php.
 *
 * type: text | number | textarea | select (options come from the server: optionsKey)
 */
export const FIELDS = [
  { name: 'title', label: 'Title', type: 'text', full: true, required: true },
  { name: 'category', label: 'Category', type: 'select', optionsKey: 'categories', required: true },
  { name: 'badge', label: 'Badge (optional)', type: 'text', hint: 'e.g. NEW or Bestseller' },
  { name: 'price', label: 'Price (₹)', type: 'number', required: true },
  { name: 'old_price', label: 'Old price (₹, crossed out)', type: 'number' },
  { name: 'desc1', label: 'Short line 1', type: 'text', full: true },
  { name: 'desc2', label: 'Short line 2', type: 'text', full: true },
  { name: 'link', label: 'Link (Drive / file / app link)', type: 'text', full: true },
  {
    name: 'demo_link',
    label: 'Demo link',
    type: 'text',
    full: true,
    hint: 'For a Resume product write: template:modern  (or template:classic / template:compact)',
  },
  { name: 'website_link', label: 'Website link', type: 'text', full: true },
  { name: 'btn_text', label: 'Button text', type: 'text' },
  { name: 'btn_type', label: 'Button style', type: 'select', optionsKey: 'btnTypes' },
  { name: 'tnc_text', label: 'Terms text (shown if “Show terms” is on)', type: 'textarea', full: true },
  { name: 'download_msg', label: 'Message when download is off', type: 'textarea', full: true },
];

// on/off switches (inside the form and as quick switches in the product list)
export const FLAGS = [
  { name: 'allow_download', label: 'Download', inList: true },
  { name: 'app_only', label: 'App only', inList: true },
  { name: 'show_preview', label: 'Preview', inList: true },
  { name: 'grants_pyq', label: 'D2D PYQ', inList: true },
  { name: 'show_tnc', label: 'Show terms' },
  { name: 'show_report_btn', label: 'Show report button' },
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
