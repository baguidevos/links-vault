@extends('errors.layout')

@section('title', 'Session Expirée (419)')
@section('badge', 'Erreur 419 • Délai Dépassé')
@section('heading', 'Votre session a expiré')
@section('description', 'Par mesure de sécurité, votre session s\'est interrompue après une période d\'inactivité. Veuillez recharger la page pour poursuivre.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(168, 85, 247, 0.12);
        --icon-color: #c084fc;
        --icon-border: rgba(168, 85, 247, 0.3);
        --badge-bg: rgba(168, 85, 247, 0.15);
        --badge-text: #a855f7;
        --badge-border: rgba(168, 85, 247, 0.3);
        --glow-color: rgba(168, 85, 247, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <polyline points="12 6 12 12 16 14"/>
</svg>
@endsection
