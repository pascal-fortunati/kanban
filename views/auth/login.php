<?php
declare(strict_types=1);
?>
<div class="auth-card max-w-md w-full bg-gray-800 p-6 rounded shadow-xl">
    <h1 class="text-xl font-semibold mb-4 text-gray-100">Connexion</h1>
    <form id="login-form" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="login-email">Email</label>
            <input id="login-email" type="email" name="email" placeholder="email@exemple.com" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required autocomplete="email" maxlength="255">
        </div>
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="login-password">Mot de passe</label>
            <input id="login-password" type="password" name="password" placeholder="Votre mot de passe" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required autocomplete="current-password">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 p-2 rounded text-white font-medium">Se connecter</button>
        <div id="login-error" class="text-red-400 text-sm mt-2"></div>
        <div class="text-sm text-gray-400 mt-2">Pas encore de compte ? <a href="<?= $baseUrl ?>/auth/register" class="text-blue-400 hover:text-blue-300">Créer un compte</a></div>
    </form>
</div>