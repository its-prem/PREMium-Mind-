import React, { useState } from 'react';
import PageFlipSound from './PageFlipSound';
import PageIndicator from './PageIndicator';
import ReadingModes from './ReadingModes';
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
  // The "Page N se aage padh rahe ho" message (ResumeReading) is switched off. To turn it on again: import ResumeReading
  // from './ResumeReading'; and add { id: 'resume', Component: ResumeReading }, to this list.
  { id: 'pager', Component: PageIndicator },
  { id: 'flip', Component: PageFlipSound },
  { id: 'modes', Component: ReadingModes },
  // Highlighter is switched off. To turn it on again: import Highlighter from './Highlighter'; and add
  // { id: 'highlighter', Component: Highlighter }, back to this list (the files are still in this folder).
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
