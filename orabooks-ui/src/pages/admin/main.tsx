import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import AdminRoutes from './routes';
import '@/styles/index.css';

const router = createHashRouter([
  {
    path: '/admin/*',
    element: <AdminRoutes />,
  },
]);

ReactDOM.createRoot(document.getElementById('orabooks-app-root')!).render(<RouterProvider router={router} />);
