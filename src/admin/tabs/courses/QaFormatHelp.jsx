/**
 * "How to write the questions" box shown above the Questions & answers text box of an Important Questions product:
 * the format, an example that can be loaded with one click, and a prompt to copy into ChatGPT / Claude.
 * The format is the one read by src/lib/qaParse.js (the website) and the Microsheet tool.
 */
import React, { useState } from 'react';

export const QA_EXAMPLE = `Q.1 Explain the Software Development Life Cycle (SDLC). (5 marks)
1. Requirement: In this step the needs of the user are collected and written down.
2. Design: The structure of the software is planned (screens, database, modules).
3. Coding: Programmers write the code as per the design.
4. Testing: The software is checked to find and remove errors.
5. Maintenance: After delivery, errors are fixed and new features are added.

Q.2 Differentiate between compiler and interpreter. (4 marks)
1. Compiler: It translates the whole program at once and then runs it. It is faster in running.
2. Interpreter: It translates and runs the program line by line. It is easier for finding errors.

Q.3 What is a database? Write any two advantages.
A database is an organised collection of data that can be stored, searched and updated easily.
1. Less repetition: The same data is not stored again and again.
2. Better security: Only allowed people can see or change the data.`;

export const QA_PROMPT = `Convert the questions and answers I give below into PLAIN TEXT in exactly this format. Output only the text, inside one code block, nothing else.

FORMAT RULES
1. Every question starts on its own line as: Q.1 <question text>   (then Q.2, Q.3 ... in order). Put the marks at the end if given, like (5 marks).
2. The answer comes in the lines below the question, one line for each point.
3. Write answer points as numbered lines: 1. Heading: explanation   (the short heading before the colon is optional; if the answer is a paragraph, write it as normal lines without numbers).
4. Leave one empty line between two questions.
5. No tables, no bold or markdown symbols (no **, no #), no bullet signs (no -, no *), no extra text before or after.
6. Keep my original wording; do not add or remove content. If a point is very long, split it into two numbered lines.

EXAMPLE OUTPUT
Q.1 Explain the Software Development Life Cycle (SDLC). (5 marks)
1. Requirement: In this step the needs of the user are collected.
2. Design: The structure of the software is planned.
3. Coding: Programmers write the code as per the design.

Q.2 What is a database?
A database is an organised collection of data that can be searched and updated easily.

QUESTIONS AND ANSWERS (paste below this line)
`;

export default function QaFormatHelp({ hasText, onUseExample, toast }) {
  const [open, setOpen] = useState(false);

  const copy = async (text, done) => {
    try {
      await navigator.clipboard.writeText(text);
      toast(done);
    } catch {
      toast('Could not copy. Select the text and copy it.', 'error');
    }
  };

  return (
    <div className="adm-qahelp">
      <button type="button" className="adm-qahelp-head" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        <b>How to write the questions (format)</b>
        <span>{open ? 'Hide' : 'Show'}</span>
      </button>
      {open && (
        <div className="adm-qahelp-body">
          <ol>
            <li>
              Every question starts on a new line with <b>Q.1</b>, <b>Q.2</b>, <b>Q.3</b> … (<b>Question 1</b> also works), then the question.
            </li>
            <li>The lines below it are the answer: one point on each line.</li>
            <li>
              A numbered point can have a heading: <b>1. Heading: explanation</b>. The heading before the colon is shown in bold.
            </li>
            <li>A line <b>Answer: text</b> is also fine; the word "Answer:" is removed.</li>
            <li>Leave an empty line between two questions. No tables or markdown signs.</li>
          </ol>
          <pre className="adm-qahelp-example">{QA_EXAMPLE}</pre>
          <div className="adm-row" style={{ gap: 8, flexWrap: 'wrap' }}>
            <button
              type="button"
              className="adm-btn small"
              onClick={() => {
                if (hasText && !window.confirm('The box already has text. Replace it with the example?')) return;
                onUseExample(QA_EXAMPLE);
                toast('Example loaded. Replace it with your own questions.');
              }}
            >
              Load example
            </button>
            <button type="button" className="adm-btn small ghost" onClick={() => copy(QA_EXAMPLE, 'Example copied')}>
              Copy example
            </button>
            <button type="button" className="adm-btn small good" onClick={() => copy(QA_PROMPT, 'Prompt copied. Paste it in ChatGPT / Claude with your questions.')}>
              Copy prompt for ChatGPT / Claude
            </button>
          </div>
          <span className="adm-hint">
            Have the questions in a PDF or notebook? Copy the prompt, paste it in an AI chat with your raw questions under it, and paste the answer in the box below.
          </span>
        </div>
      )}
    </div>
  );
}
