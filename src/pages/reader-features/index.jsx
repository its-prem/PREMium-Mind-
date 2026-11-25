import React, { useState } from 'react';
import Highlighter from './Highlighter';
import PageFlipSound from './PageFlipSound';
import PageIndicator from './PageIndicator';
import ReadingModes from './ReadingModes';
import ResumeReading from './ResumeReading';
import StudyTimer from './StudyTimer';

/**
 * Optional reader features. Each one is a self-contained component in this
 * folder. To turn a feature off, delete (or comment out) its line below.
 * To remove the whole set, delete this folder and the <ReaderFeatures />
 * line in Watch.jsx - the reader works fine without it.
 *
 * Every feature receives: scrollRef, pagesRef, docKey, ready, dock.
 */
const FEATURES = [
  { id: 'resume', Component: ResumeReading },
  { id: 'pager', Component: PageIndicator },
  { id: 'flip', Component: PageFlipSound },
  { id: 'modes', Component: ReadingModes },
  { id: 'highlighter', Component: Highlighter },
  { id: 'timer', Component: StudyTimer },
];

export default function ReaderFeatures(props) {
  // Floating button column on the right edge (features portal into it).
  const [dock, setDock] = useState(null);

  return (
    <>
      <div className="rdx-dock" ref={setDock} />
      {FEATURES.map(({ id, Component }) => (
        <Component key={id} {...props} dock={dock} />
      ))}
    </>
  );
}
