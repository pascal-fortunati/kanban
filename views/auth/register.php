<?php
declare(strict_types=1);
?>
<div class="auth-card max-w-md w-full bg-gray-800 p-6 rounded shadow-xl">
    <h1 class="text-xl font-semibold mb-4 text-gray-100">Inscription</h1>
    <form id="register-form" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="reg-name">Nom</label>
            <input id="reg-name" type="text" name="name" placeholder="Votre nom" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required minlength="2" maxlength="100" autocomplete="name">
        </div>
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="reg-email">Email</label>
            <input id="reg-email" type="email" name="email" placeholder="email@exemple.com" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required autocomplete="email" maxlength="255">
        </div>
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="reg-password">Mot de passe</label>
            <input id="reg-password" type="password" name="password" placeholder="Min. 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required minlength="8" autocomplete="new-password" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" title="Au moins une majuscule, une minuscule et un chiffre">
            <div class="text-xs text-gray-400 mt-1">Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.</div>
        </div>
        <div>
            <label class="block text-sm text-gray-300 mb-1" for="reg-confirm">Confirmation</label>
            <input id="reg-confirm" type="password" name="confirm" placeholder="Confirmez le mot de passe" class="w-full bg-gray-700 p-2 rounded text-gray-100 placeholder-gray-400" required autocomplete="new-password">
            <div id="register-hint" class="text-yellow-300 text-xs mt-1"></div>
        </div>
        <button type="submit" class="w-full bg-green-600 hover:bg-green-500 p-2 rounded text-white font-medium">Créer le compte</button>
        <div id="register-error" class="text-red-400 text-sm mt-2"></div>
        <div class="text-sm text-gray-400 mt-2">Déjà inscrit ? <a href="<?= $baseUrl ?>/auth/login" class="text-blue-400 hover:text-blue-300">Se connecter</a></div>
    </form>
</div>