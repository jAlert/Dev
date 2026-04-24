import './bootstrap';
import { mountEditors } from './text-editor';
import Sortable from 'sortablejs';
import mammoth from 'mammoth/mammoth.browser.js';

// Expose as globals so blade inline scripts can access them
window.Sortable = Sortable;
window.mammoth  = mammoth;

// app.js is a type="module" script (deferred). Livewire.js is a synchronous
// script injected at end of <body> — it runs and fires all init events BEFORE
// this module executes. So we call mountEditors() directly here (no listener
// needed) and register the morph hook directly (Livewire is already ready).
mountEditors();

Livewire.hook('morph.updated', () => setTimeout(mountEditors, 0));

document.addEventListener('livewire:navigated', mountEditors);
