# 프록시 IP 설정

관리 코드와 위젯 코드는 classes/clientip.php의 같은 IP 판정 함수를 사용합니다. 기본값은 어떤 프록시도 신뢰하지 않으며, 이 경우 X-Forwarded-For 대신 REMOTE_ADDR을 씁니다.

위젯만 불러오는 고객 페이지에서는 $_CONFIGS가 없을 수 있습니다. Minitalk.preset.php에 설정을 추가할 때 두 객체가 먼저 존재하도록 하고, 두 쪽에 같은 목록을 지정하세요. 기존 키·DB 설정은 유지하세요.

```php
// 실제 프록시의 IP 또는 전용 서브넷 CIDR을 넣으세요. 빈 배열은 프록시를 신뢰하지 않습니다.
$minitalkTrustedProxies = array();
$_CONFIGS ??= new stdClass();
$_MINITALK ??= new stdClass();
$_CONFIGS->trustedProxies = $minitalkTrustedProxies;
$_MINITALK->trustedProxies = $minitalkTrustedProxies;
unset($minitalkTrustedProxies);
```

위젯은 $_MINITALK->trustedProxies를 우선합니다. 해당 설정이 없으면 $_CONFIGS->trustedProxies를 사용합니다. 명시적인 빈 배열은 우선 설정이며 다른 쪽 설정으로 대체되지 않습니다.

전체 인터넷 대역을 신뢰 목록에 넣지 마세요. 실제 요청 경로의 프록시만 지정하고, XFF를 오른쪽부터 확인하여 신뢰 프록시 앞의 첫 비신뢰 IP를 사용합니다. 신뢰된 프록시가 아닌 요청, 잘못된 XFF, XFF가 없는 요청은 접속지 IP를 사용합니다. CloudFront와 ALB를 함께 쓰면 실제 경로의 각 프록시를 고려해야 합니다.

이 예시는 문서이며 운영 preset 파일이나 신뢰 대역을 자동으로 변경하지 않습니다. 최신 패키지를 배포할 때 classes/clientip.php를 함께 포함해야 합니다.