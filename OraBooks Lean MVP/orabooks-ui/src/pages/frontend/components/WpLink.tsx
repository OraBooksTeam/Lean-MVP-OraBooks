import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { toWpUrl } from '../lib/wp-routing';

type WpLinkProps = Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'> & {
  to: string;
  children: ReactNode;
};

export default function WpLink({ to, children, className, ...rest }: WpLinkProps) {
  const safeTo = typeof to === 'string' && to.trim() !== '' ? to : '/dashboard';
  return (
    <a href={toWpUrl(safeTo)} className={className} {...rest}>
      {children}
    </a>
  );
}
