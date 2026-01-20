// Keep in sync with config/roles.php.
const roleKeys = ['user', 'maker', 'moderator', 'admin'] as const;

export type RoleKey = (typeof roleKeys)[number];

export const getRoleKey = (role?: string): RoleKey => {
    const normalized = role ? role.toLowerCase() : '';
    return (roleKeys as readonly string[]).includes(normalized) ? (normalized as RoleKey) : 'user';
};
