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
  { id: 'dashboard', desc: 'Sales, students and what happened lately', label: 'Dashboard', icon: 'dashboard', component: lazy(() => import('./tabs/Dashboard.jsx')) },
  { id: 'courses', desc: 'Add, edit and manage everything you sell', label: 'Products', icon: 'box', component: lazy(() => import('./tabs/Courses.jsx')) },
  { id: 'ranking', desc: 'Drag products into the order shown on the home page', label: 'Home order', icon: 'sort', component: lazy(() => import('./tabs/Ranking.jsx')) },
  { id: 'students', desc: 'Search students and give or take away products', label: 'Students', icon: 'users', component: lazy(() => import('./tabs/Students.jsx')) },
  { id: 'orders', desc: 'Every payment, with a check against Cashfree', label: 'Payments', icon: 'card', component: lazy(() => import('./tabs/Orders.jsx')) },
  { id: 'reports', desc: 'Problems students have sent you', label: 'Reports', icon: 'flag', badge: 'reports', component: lazy(() => import('./tabs/Reports.jsx')) },
  { id: 'maintenance', desc: 'Close the website for everyone while you update it', label: 'Maintenance', icon: 'tools', component: lazy(() => import('./tabs/Maintenance.jsx')) },
  // A link to a page outside this admin (opens in a new tab). Delete the line to remove it.
  { id: 'd2d-pyq', label: 'D2D PYQ Access', icon: 'lock', href: 'https://premind.diplomawallah.in/d2d_pyq_admin.php' },
];
