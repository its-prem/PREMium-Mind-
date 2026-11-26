/**
 * Terms & conditions shown at checkout when a product has "Show T&C checkbox" ON but no text of its own.
 * The admin form's "Load default sample" button uses the same text.
 */
export const DEFAULT_TNC = `1. Non-Refundable: All sales are final. No refunds will be provided once the course access is granted.
2. No Sharing: Sharing your account details with others is strictly prohibited and will lead to an instant permanent ban without refund.
3. Personal Use: The study material is for your personal use only. Distribution or piracy will invite legal actions.
4. Download Policy: You can download the content only one day before the exam at 6:00 PM when the download button unlocks.
5. Payment Instruction: After successful payment, please wait for 10 seconds on the same page. Then check your profile for access to the purchased content.
6. Important Notice: This content is based on analysis and predictions; exact exam questions are not guaranteed.
7. Agreement: By proceeding, you agree to PREMium Mind's standard terms of service.`;

/** "1. Title: text" lines -> [{ no: '1.', title: 'Title', text: 'text' }]. Lines without a title stay as plain text. */
export function parseTerms(raw) {
  return String(raw || '')
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean)
    .map((line) => {
      const m = line.match(/^(\d+[.)]\s*)?([^:]{1,60}):\s*(.+)$/);
      if (m) return { no: (m[1] || '').trim(), title: m[2].trim(), text: m[3].trim() };
      const n = line.match(/^(\d+[.)])\s*(.+)$/);
      return n ? { no: n[1], title: '', text: n[2] } : { no: '', title: '', text: line };
    });
}
