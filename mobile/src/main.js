import './style.css';
import { apiBaseUrl } from './config/api.js';

document.documentElement.dataset.apiConfigured = String(Boolean(apiBaseUrl));

document.querySelector('#app').innerHTML = `
    <section class="welcome" aria-labelledby="app-title">
        <div class="picture-grid" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>
        <h1 id="app-title">Four Pictures<br />One Word</h1>
    </section>
`;
