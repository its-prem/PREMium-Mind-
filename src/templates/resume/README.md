# Resume templates (drop-in)

To add a new resume template:

1. Make a file in this folder, for example `Sunrise.jsx`. The file name (small letters) is the template id: `sunrise`.
2. The file exports one React component. It gets two props and draws ONE A4 page (794 x 1123 px):

```jsx
import React from 'react';

export default function Sunrise({ data, accent }) {
  return (
    <div className="rs-doc" style={{ '--accent': accent }}>
      <h1>{data.name || 'Your Name'}</h1>
      <p>{data.title}</p>
      {/* data: name, title, email, phone, location, link, summary, skills[], experience[], education[], projects[] */}
    </div>
  );
}
```

   - `data.experience[]` items: `{ id, role, company, period, points }` (`points` is text, one line per point) (see `BLANK_RESUME` in `src/components/ResumeDocument.jsx`).
   - You can import the small helpers or the CSS of `ResumeDocument` if you want the same look.
**Click to edit in the preview (optional but recommended):** put a `data-edit` attribute on every text the student should be able to click and change right in the preview:

```jsx
<h1 data-edit="name">{data.name}</h1>
<p data-edit="summary" data-multiline="1">{data.summary}</p>        {/* data-multiline: Enter makes a new line */}
<li data-edit={`skills.${i}`}>{skill}</li>
<b data-edit={`experience.${i}.role`}>{e.role}</b>
<li data-edit={`experience.${i}.points#${j}`}>{point}</li>  {/* the j-th line of the points */}
```

Paths: `name`, `title`, `email`, `phone`, `location`, `link`, `summary`, `skills.N`, `experience.N.(role|company|period)`, `experience.N.points#J`, `education.N.(degree|school|period|score)`, `projects.N.(name|desc)`. A text without `data-edit` can only be changed from the form.

3. Admin panel → Products → Add product → Category **Resume Templates**:
   - Demo link: `template:sunrise`
   - Selling price: the amount the student pays to download the PDF (customizing is free)
   - Image: a preview picture of the template (the store card shows this picture for dropped-in templates)
4. Push to GitHub. Netlify publishes it.

Security: a dropped-in template is not loaded on the home page or for logged-out visitors, and the clean PDF copy
exists only for a buyer (the server confirms the purchase). A preview before payment carries a watermark.
