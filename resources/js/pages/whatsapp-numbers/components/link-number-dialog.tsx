import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { WaNumber } from '@/types/wa-number';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { LoaderCircleIcon } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const CONNECTED_STATUSES = ['connected', 'logged_in'];

export default function LinkNumberDialog({ number, onOpenChange }: { number: WaNumber | null; onOpenChange: (open: boolean) => void }) {
  const [qr, setQr] = useState<string | null>(null);
  const [qrLoading, setQrLoading] = useState(false);
  const [phone, setPhone] = useState('');
  const [pairingCode, setPairingCode] = useState<string | null>(null);
  const [pairingLoading, setPairingLoading] = useState(false);

  useEffect(() => {
    if (!number) {
      setQr(null);
      setPairingCode(null);
      setPhone('');
      return;
    }

    setQrLoading(true);
    axios
      .get(`/settings/whatsapp-numbers/${number.id}/qr`)
      .then((response) => setQr(response.data.qr))
      .finally(() => setQrLoading(false));
  }, [number]);

  // Neither QR scanning nor pairing-code entry happens over this connection
  // - the phone talks to WhatsApp directly, GoWA just observes the result -
  // so there is no request/response moment to react to here. Poll the
  // number's live status while the dialog is open and close it once the
  // phone has actually finished connecting.
  useEffect(() => {
    if (!number) return;

    const poll = () => {
      axios.get(`/settings/whatsapp-numbers/${number.id}/status`).then((response) => {
        if (CONNECTED_STATUSES.includes(response.data.status)) {
          router.reload({ only: ['numbers'] });
          onOpenChange(false);
        }
      });
    };

    const interval = setInterval(poll, 3000);
    return () => clearInterval(interval);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [number]);

  const requestPairingCode = (e: FormEvent) => {
    e.preventDefault();
    if (!number) return;

    setPairingLoading(true);
    axios
      .post(`/settings/whatsapp-numbers/${number.id}/pairing-code`, { phone })
      .then((response) => setPairingCode(response.data.code))
      .finally(() => setPairingLoading(false));
  };

  return (
    <Dialog open={!!number} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Link WhatsApp number</DialogTitle>
          <DialogDescription>Scan a QR code or enter a pairing code from your phone.</DialogDescription>
        </DialogHeader>

        <Tabs defaultValue="qr" className="p-4 pt-0">
          <TabsList className="mb-4">
            <TabsTrigger value="qr">QR code</TabsTrigger>
            <TabsTrigger value="pairing-code">Pairing code</TabsTrigger>
          </TabsList>

          <TabsContent value="qr">
            <div className="flex min-h-48 items-center justify-center">
              {qrLoading && <LoaderCircleIcon className="animate-spin" />}
              {!qrLoading && qr && (typeof qr === 'string' && qr.startsWith('data:image') ? <img src={qr} alt="QR code" /> : <code className="text-xs break-all">{qr}</code>)}
              {!qrLoading && !qr && <p className="text-muted-foreground text-sm">Unable to load QR code.</p>}
            </div>
          </TabsContent>

          <TabsContent value="pairing-code">
            {!pairingCode ? (
              <form onSubmit={requestPairingCode} className="grid gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="phone">Phone number</Label>
                  <Input id="phone" type="text" placeholder="62812xxxxxxx" value={phone} onChange={(e) => setPhone(e.target.value)} />
                </div>
                <Button type="submit" disabled={pairingLoading || !phone}>
                  {pairingLoading && <LoaderCircleIcon className="animate-spin" />}
                  Request pairing code
                </Button>
              </form>
            ) : (
              <div className="flex flex-col items-center gap-2 py-6">
                <p className="text-muted-foreground text-sm">Enter this code on your phone</p>
                <p className="text-2xl font-semibold tracking-widest">{pairingCode}</p>
              </div>
            )}
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}
