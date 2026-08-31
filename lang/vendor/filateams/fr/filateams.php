<?php

declare(strict_types=1);

return [
    'pages' => [
        'edit_team'   => ['label' => 'Paramètres de l\'équipe'],
        'create_team' => ['label' => 'Créer une équipe'],
    ],

    'fields' => [
        'team_name'     => ['label' => 'Nom de l\'équipe'],
        'name'          => ['label' => 'Nom'],
        'email'         => ['label' => 'Email'],
        'email_address' => ['label' => 'Adresse email'],
        'role'          => ['label' => 'Rôle'],
        'invited_by'    => ['label' => 'Invité par'],
        'expires'       => ['label' => 'Expire le'],
    ],

    'sections' => [
        'delete_team' => ['heading' => 'Supprimer l\'équipe'],
    ],

    'tables' => [
        'members' => [
            'heading' => 'Membres de l\'équipe',
        ],
        'invitations' => [
            'heading'     => 'Invitations en attente',
            'empty_state' => [
                'heading'     => 'Aucune invitation en attente',
                'description' => 'Invitez des membres de l\'équipe en cliquant sur le bouton ci-dessus.',
            ],
        ],
    ],

    'actions' => [
        'delete_team' => [
            'label'              => 'Supprimer l\'équipe',
            'modal_heading'      => 'Supprimer l\'équipe',
            'modal_description'  => 'Êtes-vous sûr de vouloir supprimer cette équipe ? Cette action est irréversible.',
            'modal_submit_label' => 'Supprimer l\'équipe',
        ],
        'change_role'       => ['label' => 'Modifier le rôle'],
        'remove_member'     => ['label' => 'Retirer'],
        'leave_team'        => ['label' => 'Quitter l\'équipe'],
        'invite_member'     => ['label' => 'Inviter un membre'],
        'cancel_invitation' => ['label' => 'Annuler'],
    ],

    'notifications' => [
        'cannot_delete_personal_team' => ['title' => 'Impossible de supprimer l\'équipe personnelle.'],
        'role_updated'                => ['title' => 'Rôle mis à jour.'],
        'member_removed'              => ['title' => 'Membre retiré.'],
        'left_team'                   => ['title' => 'Vous avez quitté l\'équipe.'],
        'invitation_sent'             => ['title' => 'Invitation envoyée à :email.'],
        'invitation_cancelled'        => ['title' => 'Invitation annulée.'],
    ],

    'validation' => [
        'team_name' => [
            'reserved'       => 'Ce nom d\'équipe est réservé et ne peut pas être utilisé.',
            'route_conflict' => 'Ce nom d\'équipe entre en conflit avec une route existante.',
        ],
        'invitation' => [
            'already_member' => 'Cet utilisateur est déjà membre de l\'équipe.',
            'pending_exists' => 'Une invitation en attente existe déjà pour cet email.',
        ],
    ],

    'mail' => [
        'invitation' => [
            'subject'       => 'Vous avez été invité à rejoindre :team',
            'line_invited'  => ':inviter vous a invité à rejoindre l\'équipe :team.',
            'action_accept' => 'Accepter l\'invitation',
            'line_expiry'   => 'Cette invitation expirera le :date.',
        ],
    ],

    'flash' => [
        'invitation_expired'     => 'Cette invitation a expiré.',
        'invitation_wrong_email' => 'Cette invitation a été envoyée à une autre adresse email.',
        'no_team'                => 'Vous devez être membre d\'une équipe pour accéder à cette ressource.',
        'not_member_of_any_team' => 'Vous n\'êtes membre d\'aucune équipe.',
    ],

    'personal_team_name' => 'Espace de :name',

    'roles' => [
        'owner'  => 'Propriétaire',
        'admin'  => 'Administrateur',
        'member' => 'Membre',
    ],
    'Name' => 'Nom',
    'Slug' => 'Slug',
    'Color' => 'Couleur',
    'Icon' => 'Icône',
    'Description' => 'Description',
    'Sort Order' => 'Ordre de tri',
];
