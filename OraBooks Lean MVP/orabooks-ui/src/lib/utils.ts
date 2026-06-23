export function cn(...classes: (string | undefined | false)[]) {
  return classes.filter(Boolean).join(' ');
}

export {
  buildOrgUrl,
  getTenantBaseDomain,
  getTenantDomainSuffix,
} from '@/lib/residency/sl004';
