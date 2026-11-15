import './stimulus_bootstrap.js';
/*
 * Point d'entrée JavaScript de l'application.
 * La feuille de style (assets/styles/app.css) est chargée par templates/base.html.twig,
 * pour être disponible aussi sur les pages sans JavaScript (connexion, écran de salle).
 */

// Menu sur une ligne défilante (téléphone) : l'entrée active reste visible
const activeLink = document.querySelector('.topbar__links .is-active');
const links = activeLink?.parentElement;
if (links && links.scrollWidth > links.clientWidth) {
    links.scrollLeft = activeLink.offsetLeft - (links.clientWidth - activeLink.offsetWidth) / 2;
}
