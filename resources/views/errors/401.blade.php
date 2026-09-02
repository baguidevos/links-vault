@extends('errors.layout')

@section('title', 'Authentification Requise (401)')
@section('badge', 'Erreur 401 • Non Authentifié')
@section('heading', 'Veuillez vous connecter')
@section('description', 'Cette ressource est protégée. Vous devez être connecté avec votre compte LinksVault pour y accéder.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(59, 130, 246, 0.12);
        --icon-color: #60a5fa;
        --icon-border: rgba(59, 130, 246, 0.3);
        --badge-bg: rgba(59, 130, 246, 0.15);
        --badge-text: #3b82f6;
        --badge-border: rgba(59, 130, 246, 0.3);
        --glow-color: rgba(59, 130, 246, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
    <circle cx="12" cy="7" r="4"/>
</svg>
@endsection
