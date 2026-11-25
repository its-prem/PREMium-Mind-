/**
 * THE LIST OF ADMIN TABS.
 *
 * To add a tab:
 *   1. make a file in src/admin/tabs/ (copy any small one, e.g. Reports.jsx)
 *   2. add one line below.
 * To remove a tab: delete its line. To rename: change `label`. Order here = order on screen.
 */
import { lazy } from 'react';

export const TABS = [
  { id: 'dashboard', label: 'Dashboard', icon: 'dashboard', component: lazy(() => import('./tabs/Dashboard.jsx')) },
  { id: 'courses', label: 'Products', icon: 'box', component: lazy(() => import('./tabs/Courses.jsx')) },
  { id: 'ranking', label: 'Home order', icon: 'sort', component: lazy(() => import('./tabs/Ranking.jsx')) },
  { id: 'students', label: 'Students', icon: 'users', component: lazy(() => import('./tabs/Students.jsx')) },
  { id: 'orders', label: 'Payments', icon: 'card', component: lazy(() => import('./tabs/Orders.jsx')) },
  { id: 'reports', label: 'Reports', icon: 'flag', component: lazy(() => import('./tabs/Reports.jsx')) },
];
