<?php

namespace App\Security;

/**
 * Jeton CSRF commun aux actions de l'administration qui ne passent pas par un formulaire Symfony
 * (tirage, enchaînement des parties, démarque, resets, attribution de cartons).
 *
 * Les vues l'envoient dans le champ « _token » ; le JavaScript le lit dans la balise meta « csrf-token ».
 */
final class AdminCsrf
{
    public const TOKEN_ID = 'admin_action';
}
