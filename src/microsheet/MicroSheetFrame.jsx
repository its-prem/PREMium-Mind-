/**
 * The Microsheet tool (microsheet.html) inside a frame, with the text and the download rule sent in.
 *
 *   <MicroSheetFrame ref={ref} subject="Project Management" text={...} canDownload={true} lockedMessage="…" />
 *
 *   mode="viewer"   what STUDENTS get: only the finished A4 pages, like a PDF (no editor, no text box, no copy).
 *                   Without it the full tool is shown (the admin Microsheet dashboard).
 *   dark            viewer background: dark / light
 *   watermark       the buyer's own details, shown faintly over the pages (viewer)
 *   blurred         blur the pages (viewer), for example while the window is not in focus
 *
 *   ref.current.requestText()        -> { text, subject }: what is in the tool's text box right now
 *   ref.current.send({ type: 'export', format: 'pdf' | 'docx' })   -> download / print (works only while the download is open)
 *
 * To change how the tool looks or works, edit microsheet.html in this folder.
 */
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import toolHtml from './microsheet.html?raw';

const MicroSheetFrame = forwardRef(function MicroSheetFrame(
  { subject = '', text = '', canDownload = false, lockedMessage = '', mode = 'editor', dark = false, watermark = '', blurred = false, className = '', onEvent },
  ref
) {
  const frameRef = useRef(null);
  const [ready, setReady] = useState(false);
  const waiting = useRef(null);
  const onEventRef = useRef(onEvent);
  onEventRef.current = onEvent;

  const post = (payload) => {
    frameRef.current?.contentWindow?.postMessage({ source: 'pm-microsheet-host', ...payload }, '*');
  };

  // answers from the tool
  useEffect(() => {
    const onMessage = (ev) => {
      if (ev.source !== frameRef.current?.contentWindow) return;
      const d = ev.data;
      if (!d || d.source !== 'pm-microsheet') return;
      if (d.type === 'ready') setReady(true);
      onEventRef.current?.(d); // for example { type: 'export-state', state: 'busy' | 'done' | 'failed', message }
      if (d.type === 'text' && waiting.current) {
        waiting.current({ text: d.text, subject: d.subject });
        waiting.current = null;
      }
    };
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, []);

  // fill the tool when it is ready, and again when the product / text changes
  useEffect(() => {
    if (ready) post({ type: 'init', subject, text, canDownload, lockedMessage, mode, dark, watermark });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, subject, text, mode]);

  // the download rule can change by itself (an unlock time passes)
  useEffect(() => {
    if (ready) post({ type: 'download', canDownload, lockedMessage });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, canDownload, lockedMessage]);

  useEffect(() => {
    if (ready && mode === 'viewer') post({ type: 'theme', dark });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, dark]);
  useEffect(() => {
    if (ready && mode === 'viewer') post({ type: 'watermark', text: watermark });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, watermark]);
  useEffect(() => {
    if (ready && mode === 'viewer') post({ type: 'blur', on: blurred });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ready, blurred]);

  useImperativeHandle(ref, () => ({
    send: (payload) => post(payload),
    requestText: () =>
      new Promise((resolve) => {
        waiting.current = resolve;
        post({ type: 'getText' });
        setTimeout(() => {
          if (waiting.current) {
            waiting.current(null);
            waiting.current = null;
          }
        }, 3000);
      }),
  }));

  return <iframe ref={frameRef} title="Microsheet" className={className} srcDoc={toolHtml} onLoad={() => setReady(true)} style={{ border: 0, width: '100%', height: '100%', display: 'block' }} />;
});

export default MicroSheetFrame;
