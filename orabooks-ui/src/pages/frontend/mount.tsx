import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import './styles/index.css';

function mount(ui: React.ReactNode) {
  const el = document.getElementById('orabooks-app-root');
  if (!el) return;
  ReactDOM.createRoot(el).render(
    <BrowserRouter>{ui}</BrowserRouter>
  );
}

export { mount };
