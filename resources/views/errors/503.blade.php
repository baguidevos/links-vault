@extends('errors.layout')

@section('title', 'Maintenance en Cours (503)')
@section('badge', 'Erreur 503 • Maintenance Programmée')
@section('heading', 'LinksVault fait peau neuve !')
@section('description', 'Nous procédons actuellement à une maintenance et mise à jour de notre plateforme. Le service sera de nouveau disponible dans un court instant.')

@section('icon')
<style>
    :root {
        --icon-bg: rgba(14, 165, 233, 0.12);
        --icon-color: #38bdf8;
        --icon-border: rgba(14, 165, 233, 0.3);
        --badge-bg: rgba(14, 165, 233, 0.15);
        --badge-text: #0284c7;
        --badge-border: rgba(14, 165, 233, 0.3);
        --glow-color: rgba(14, 165, 233, 0.2);
    }
</style>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
</svg>
@endsection
