MCQ tests admin tab - PARKED (not used right now, kept for later)

To bring it back:
  MockTests.tab.jsx        -> src/admin/tabs/MockTests.jsx
  mcqParse.js, mockStore.js -> src/lib/
  mocktests.public.php     -> server/mocktests.php        (upload to Hostinger premind/mocktests.php)
  mocktests.admin.php      -> server/admin/mocktests.php  (upload to Hostinger premind/admin/mocktests.php)
  src/admin/registry.js: add the line
    { id: 'mocktests', desc: 'Paste MCQ questions as text and upload a mock test to the website', label: 'MCQ tests', icon: 'check', component: lazy(() => import('./tabs/MockTests.jsx')) },
  src/pages/MockTests.jsx: import { useUploadedMocks } from '../lib/mockStore'; and call useUploadedMocks(); at the top of the component.
