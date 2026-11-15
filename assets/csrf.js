/**
 * Jeton CSRF des actions d'administration, lu dans la balise <meta name="csrf-token"> du gabarit admin.
 * À joindre à toute requête qui modifie des données (champ « _token » du corps de la requête).
 */
export function adminCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
