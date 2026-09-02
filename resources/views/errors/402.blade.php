@extends('errors.layout')

@section('title', 'Abonnement Requis (402)')
@section('badge', 'Erreur 402 • Quota Dépassé')
@section('heading', 'Limite de forfait atteinte')
@section('description', 'Vous avez atteint la limite de votre forfait actuel. Passez à une formule supérieure pour débloquer davantage de fonctionnalités et de stockage.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(16, 185, 129, 0.12);
        --icon-color: #34d399;
        --icon-border: rgba(16, 185, 129, 0.3);
        --badge-bg: rgba(16, 185, 129, 0.15);
        --badge-text: #10b981;
        --badge-border: rgba(16, 185, 129, 0.3);
        --glow-color: rgba(16, 185, 129, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <rect x="2" y="5" width="20" height="14" rx="2"/>
    <line x1="2" y1="10" x2="22" y2="10"/>
</svg>
@endsection
