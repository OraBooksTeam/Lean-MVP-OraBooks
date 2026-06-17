import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import FrontendRoutes from './App';
import '@/styles/index.css';

const router = createHashRouter([
  {
    path: '/',
    element: <FrontendRoutes />,
    children: [
      { path: '/', element: <div /> },
      { path: '/login', element: <div /> },
      { path: '/register', element: <div /> },
      { path: '/tier-selection', element: <div /> },
      { path: '/dashboard', element: <div /> },
      { path: '/customers', element: <div /> },
      { path: '/invoices', element: <div /> },
      { path: '/chart-of-accounts', element: <div /> },
      { path: '/journals', element: <div /> },
      { path: '/partner-onboarding', element: <div /> },
      { path: '/notifications', element: <div /> },
      { path: '/my-exports', element: <div /> },
      { path: '/profile', element: <div /> },
    ],
  },
]);

ReactDOM.createRoot(document.getElementById('orabooks-app-root')!).render(<RouterProvider router={router} />);
