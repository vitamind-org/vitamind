import Container from '@/components/container';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
import SettingsLayout from '@/layouts/settings/layout';
import LinkNumberDialog from '@/pages/whatsapp-numbers/components/link-number-dialog';
import { PageProps } from '@/types';
import { WaNumber, WaNumberStatus } from '@/types/wa-number';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircleIcon, PlusIcon, TrashIcon, UnlinkIcon } from 'lucide-react';
import { FormEvent, useState } from 'react';

const statusVariant: Record<WaNumberStatus, 'gray' | 'warning' | 'info' | 'success'> = {
  disconnected: 'gray',
  connecting: 'warning',
  connected: 'info',
  logged_in: 'success',
};

const statusLabel: Record<WaNumberStatus, string> = {
  disconnected: 'Disconnected',
  connecting: 'Connecting',
  connected: 'Connected',
  logged_in: 'Logged in',
};

export default function WhatsAppNumbers() {
  const page = usePage<PageProps<{ numbers: WaNumber[] }>>();
  const numbers = page.props.numbers;

  const addForm = useForm({});
  const submitAdd = (e: FormEvent) => {
    e.preventDefault();
    addForm.post('/settings/whatsapp-numbers');
  };

  const [linkingNumber, setLinkingNumber] = useState<WaNumber | null>(null);

  const unlink = (number: WaNumber) => {
    router.post(`/settings/whatsapp-numbers/${number.id}/unlink`);
  };

  const destroy = (number: WaNumber) => {
    router.delete(`/settings/whatsapp-numbers/${number.id}`);
  };

  return (
    <SettingsLayout>
      <Head title="WhatsApp Numbers" />

      <Container className="max-w-5xl">
        <div className="flex items-start justify-between">
          <Heading title="WhatsApp Numbers" description="Manage the WhatsApp numbers connected to this workspace" />
          <Button onClick={submitAdd} disabled={addForm.processing}>
            {addForm.processing ? <LoaderCircleIcon className="animate-spin" /> : <PlusIcon />}
            Add number
          </Button>
        </div>

        <Card className="mt-4">
          <CardHeader>
            <CardTitle>Numbers</CardTitle>
          </CardHeader>
          <CardContent className="divide-y p-0">
            {numbers.length === 0 && <div className="text-muted-foreground p-4 text-sm">No WhatsApp numbers yet — add one to get started.</div>}
            {numbers.map((number) => (
              <CardRow key={number.id}>
                <div>
                  <p className="font-medium">{number.phone_number ?? number.device_id}</p>
                  <Badge variant={statusVariant[number.status]}>{statusLabel[number.status]}</Badge>
                </div>
                <div className="flex items-center gap-2">
                  {number.status !== 'logged_in' && (
                    <Button variant="outline" onClick={() => setLinkingNumber(number)}>
                      Link
                    </Button>
                  )}
                  {number.status === 'logged_in' && (
                    <Button variant="outline" onClick={() => unlink(number)}>
                      <UnlinkIcon />
                      Unlink
                    </Button>
                  )}
                  <Button variant="outline" onClick={() => destroy(number)}>
                    <TrashIcon />
                    Delete
                  </Button>
                </div>
              </CardRow>
            ))}
          </CardContent>
        </Card>
      </Container>

      <LinkNumberDialog number={linkingNumber} onOpenChange={(open) => !open && setLinkingNumber(null)} />
    </SettingsLayout>
  );
}
