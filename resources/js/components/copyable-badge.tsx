import { Tooltip, TooltipContent, TooltipTrigger } from '@vitamind/ui/tooltip';
import { Badge } from '@vitamind/ui/badge';
import { useClipboard } from '@/hooks/use-clipboard';

export default function CopyableBadge({ text, tooltip }: { text: string | null | undefined; tooltip?: boolean }) {
  const { copied, copy } = useClipboard();

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <div className="inline-flex cursor-pointer justify-start space-x-2 truncate" onClick={() => copy(text || '')}>
          <Badge variant={copied ? 'success' : 'outline'} className="block max-w-[200px] overflow-hidden overflow-ellipsis">
            {text}
          </Badge>
        </div>
      </TooltipTrigger>
      <TooltipContent side="top">
        <span className="flex items-center space-x-2">{tooltip ? text : 'Copy'}</span>
      </TooltipContent>
    </Tooltip>
  );
}
