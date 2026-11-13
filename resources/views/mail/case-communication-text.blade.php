{{ $body }}

@if ($portalUrl)
View or reply to your case:
{{ $portalUrl }}

@endif
@if ($reference)
---
Case reference: {{ $reference }}
@endif
