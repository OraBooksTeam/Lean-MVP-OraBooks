export type TeamRoleOption = { id: string; label: string };

export type TeamMember = {
  id: number;
  email?: string;
  role?: string;
  joined_at?: string;
  status?: string;
};

export type TeamCapabilities = {
  invite_user?: boolean;
  change_role?: boolean;
  remove_user?: boolean;
};

/** Roles that can be assigned via the team role dropdown (owner transfer is reserved). */
export function assignableMemberRoles(roles: TeamRoleOption[] = []): TeamRoleOption[] {
  return roles.filter((role) => role.id !== 'owner');
}

export function isOwnerRole(role?: string) {
  return (role || '').trim().toLowerCase() === 'owner';
}

export function canEditMemberRole(
  caps: TeamCapabilities,
  member: TeamMember,
  currentUserId?: number | null
) {
  if (!caps.change_role) return false;
  if (member.id === currentUserId) return false;
  if (isOwnerRole(member.role)) return false;
  return true;
}

export function canRemoveMember(
  caps: TeamCapabilities,
  member: TeamMember,
  currentUserId?: number | null
) {
  if (!caps.remove_user) return false;
  if (member.id === currentUserId) return false;
  if (isOwnerRole(member.role)) return false;
  return true;
}
