import { useEffect, useRef } from 'react';
import QRCode from 'qrcode';

interface TwoFactorQrCodeProps {
  value: string;
  size?: number;
  className?: string;
}

export default function TwoFactorQrCode({ value, size = 176, className }: TwoFactorQrCodeProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !value) {
      return;
    }

    void QRCode.toCanvas(canvas, value, {
      width: size,
      margin: 1,
      errorCorrectionLevel: 'M',
    });
  }, [value, size]);

  if (!value) {
    return null;
  }

  return (
    <canvas
      ref={canvasRef}
      aria-label="2FA QR code"
      className={className ?? 'mx-auto rounded-lg border border-border bg-white p-2'}
    />
  );
}
