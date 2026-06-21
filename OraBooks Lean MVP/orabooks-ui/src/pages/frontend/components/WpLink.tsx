import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { toWpUrl } from '../lib/wp-routing';

type WpLinkProps = Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'> & {
  to: string;
  children: ReactNode;
};

export default function WpLink({ to, children, className, ...rest }: WpLinkProps) {
  return (
    <a href={toWpUrl(to)} className={className} {...rest}>
      {children}
    </a>
  );
}
