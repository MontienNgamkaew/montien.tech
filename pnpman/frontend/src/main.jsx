import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  // Removing StrictMode for DnD compatibility during dev if needed, but it's okay to keep
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
