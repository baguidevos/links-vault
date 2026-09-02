@extends('errors.layout')

@section('title', 'Erreur Interne du Serveur (500)')
@section('badge', 'Erreur 500 • Dysfonctionnement Serveur')
@section('heading', 'Oups ! Un problème est survenu')
@section('description', 'Un incident technique inattendu s\'est produit lors du traitement de votre demande. L\'équipe technique a été notifiée.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(239, 68, 68, 0.12);
        --icon-color: #f87171;
        --icon-border: rgba(239, 68, 68, 0.3);
        --badge-bg: rgba(239, 68, 68, 0.15);
        --badge-text: #ef4444;
        --badge-border: rgba(239, 68, 68, 0.3);
        --glow-color: rgba(239, 68, 68, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
    <line x1="12" y1="9" x2="12" y2="13"/>
    <line x1="12" y1="17" x2="12.01" y2="17"/>
</svg>
@endsection
