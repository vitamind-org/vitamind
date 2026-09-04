import { Button } from '@vitamind/ui/button';
import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Plugin } from '@/types/plugin';
import { Tooltip, TooltipContent, TooltipTrigger } from '@vitamind/ui/tooltip';
import { PageProps } from '@/types';

export default function QuickInstall({ url }: { url: string }) {
  const form = useForm({
    url: url,
  });

  const page = usePage<PageProps<{
    plugins: Plugin[];
  }>>();

  const isInstalled = page.props.plugins.some((plugin) => plugin.repo === url);

  const submit = () => {
    form.post(route('plugins.install.github'), {
      onSuccess: () => {},
    });
  };

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button variant="default" onClick={submit} disabled={form.processing || isInstalled}>
          {form.processing && <LoaderCircleIcon className="animate-spin" />}
          Install
        </Button>
      </TooltipTrigger>
      {isInstalled && <TooltipContent>Already Installed</TooltipContent>}
    </Tooltip>
  );
}
