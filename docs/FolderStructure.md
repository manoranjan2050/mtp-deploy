# Folder Structure — MTP Deploy

Domain-oriented structure inside the standard Laravel skeleton. Each module gets its
own namespace folder under the concern it belongs to (not a monolithic "Models"
dumping ground) once the module is built — Module 1 establishes the pattern.

```
app/
├── Actions/
│   └── Auth/
│       ├── RegisterUserAction.php
│       ├── AuthenticateUserAction.php
│       ├── EnableTwoFactorAuthenticationAction.php
│       ├── ConfirmTwoFactorAuthenticationAction.php
│       ├── DisableTwoFactorAuthenticationAction.php
│       ├── CreateApiTokenAction.php
│       └── RevokeSessionAction.php
├── DTOs/
│   └── Auth/
│       ├── RegisterUserData.php
│       └── TwoFactorSetupData.php
├── Enums/
│   ├── UserStatus.php
│   └── ApiTokenAbility.php
├── Events/
│   └── Auth/
│       ├── UserRegistered.php
│       └── TwoFactorEnabled.php
├── Filament/
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── RoleResource.php
│   │   └── ...
│   ├── Pages/
│   │   ├── Auth/Login.php
│   │   ├── Auth/Register.php
│   │   ├── Profile.php
│   │   └── ...
│   └── Widgets/
├── Listeners/
│   └── Auth/
│       └── LogUserRegistration.php
├── Livewire/
│   └── Auth/
│       ├── TwoFactorChallenge.php
│       ├── SessionsManager.php
│       └── ApiTokensManager.php
├── Models/
│   ├── User.php
│   └── ...
├── Policies/
│   ├── UserPolicy.php
│   └── ...
├── Repositories/
│   ├── Contracts/
│   │   └── UserRepositoryInterface.php
│   └── UserRepository.php
├── Services/
│   ├── Auth/
│   │   ├── TwoFactorAuthenticationService.php
│   │   └── SessionManagementService.php
│   └── System/
│       └── SystemCommandService.php   # introduced Module 1, used from Module 3+
├── Support/
│   └── SystemCommand.php              # enum + value object, Module 3+
└── Providers/
    ├── AppServiceProvider.php
    ├── FilamentServiceProvider.php    # panel registration, if not using auto-discovery
    └── EventServiceProvider.php       # explicit — Laravel 12 has no auto-discovery,
                                        # see CodingStandards.md pitfall note
```

```
database/
├── migrations/
├── factories/
└── seeders/
    ├── RoleSeeder.php
    ├── PermissionSeeder.php
    └── DatabaseSeeder.php

resources/
├── views/
│   ├── livewire/
│   └── filament/
├── css/
└── js/

tests/
├── Feature/
│   └── Auth/
│       ├── LoginTest.php
│       ├── RegisterTest.php
│       ├── TwoFactorTest.php
│       ├── SessionManagementTest.php
│       └── ApiTokenTest.php
└── Unit/
    └── Actions/
        └── Auth/

docs/
├── Vision.md
├── Roadmap.md
├── Architecture.md
├── Database.md
├── API.md
├── UserFlow.md
├── Features.md
├── Security.md
├── FolderStructure.md
└── CodingStandards.md
```

## Rules
- `app/Http/Controllers` stays essentially empty — Filament/Livewire own the UI
  layer; plain controllers only exist for webhook receivers and the (future) public
  REST API in Module 17.
- No module reaches into another module's `Repositories`/`Services` directly across
  a domain boundary without going through an Action or Event — this keeps modules
  independently testable and matches the "one module at a time" build order.
