import ReactDOM from 'react-dom/client';
import { RouterProvider, createHashRouter } from 'react-router-dom';
import AdminRoutes from './routes';

export function mountStandaloneApp(root: HTMLElement) {
  const router = createHashRouter([
    {
      path: '/admin/*',
      element: <AdminRoutes />,
    },
  ]);

  ReactDOM.createRoot(root).render(<RouterProvider router={router} />);
}
