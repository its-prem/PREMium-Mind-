import React, { useState } from 'react';
import { downloadBlob } from '../lib/cdn';
import PdfViewer from './PdfViewer';

/**
 * Open / Download buttons for one or more finished files.
 * files: [{ blob, name }]
 * onRename(newBase): optional, called when the name is edited in the full-screen viewer.
 */
export default function ResultActions({ files, onMessage, onRename }) {
  const [note, setNote] = useState('');
  const [viewing, setViewing] = useState(false);
  const many = files.length > 1;
  const isPdf = !many && files[0].blob.type === 'application/pdf';

  const downloadAll = () => {
    files.forEach((f, i) => setTimeout(() => downloadBlob(f.blob, f.name), i * 350));
    setNote(many ? `${files.length} files downloading...` : '');
    onMessage?.(many ? `${files.length} files downloaded.` : `${files[0].name} downloaded.`);
  };

  return (
    <div className="tl-actions">
      <div className="tl-row">
        {isPdf && (
          <button type="button" className="tl-btn is-ghost" onClick={() => setViewing(true)}>
            <ion-icon name="expand-outline"></ion-icon> Open full screen
          </button>
        )}
        <button type="button" className="tl-btn" onClick={downloadAll}>
          <ion-icon name="download-outline"></ion-icon> {many ? `Download all (${files.length})` : 'Download'}
        </button>
      </div>
      {note && <p className="tl-help" style={{ margin: '6px 0 0' }}>{note}</p>}
      {viewing && (
        <PdfViewer
          file={files[0].blob}
          name={files[0].name}
          onRename={onRename}
          onClose={() => setViewing(false)}
        />
      )}
    </div>
  );
}
