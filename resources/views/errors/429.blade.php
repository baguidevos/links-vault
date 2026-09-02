@extends('errors.layout')

@section('title', 'Trop de Requêtes (429)')
@section('badge', 'Erreur 429 • Limite de Débit')
@section('heading', 'Ralentissez la cadence !')
@section('description', 'Un trop grand nombre de requêtes a été envoyé dans un court laps de temps. Veuillez patienter quelques instants avant de réessayer.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(249, 115, 22, 0.12);
        --icon-color: #fb923c;
        --icon-border: rgba(249, 115, 22, 0.3);
        --badge-bg: rgba(249, 115, 22, 0.15);
        --badge-text: #f97316;
        --badge-border: rgba(249, 115, 22, 0.3);
        --glow-color: rgba(249, 115, 22, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
</svg>
@endsection
