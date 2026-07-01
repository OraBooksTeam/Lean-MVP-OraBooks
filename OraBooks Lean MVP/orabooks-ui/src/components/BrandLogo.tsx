type BrandLogoProps = {
  wrapperClassName?: string;
  imageClassName?: string;
  imageStyle?: React.CSSProperties;
  alt?: string;
  fallbackClassName?: string;
  fallbackTextClassName?: string;
};

function getBrandLogoUrl(): string {
  return String((window as any).orabooks_ajax?.logo_url || '').trim();
}

export default function BrandLogo({
  wrapperClassName = '',
  imageClassName = 'h-12 w-auto object-contain',
  imageStyle,
  alt = 'OraBooks',
  fallbackClassName = '',
  fallbackTextClassName = '',
}: BrandLogoProps) {
  const logoUrl = getBrandLogoUrl();
  const wrapperClasses = ['flex justify-center', wrapperClassName].filter(Boolean).join(' ');

  if (logoUrl) {
    return (
      <div className={wrapperClasses}>
        <img src={logoUrl} alt={alt} className={imageClassName} style={imageStyle} />
      </div>
    );
  }

  return (
    <div className={wrapperClasses}>
      <div className={fallbackClassName}>
        <span className={fallbackTextClassName}>OB</span>
      </div>
    </div>
  );
}
