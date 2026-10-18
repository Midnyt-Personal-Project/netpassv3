# ==========================================
# OYALO SYNC - FILE EDITION (dual method)
# ==========================================
:local routerId "RTR-238605"
:local routerToken "oyalo_26lWGsL0vqStZ2YEOQu9PKaznlhZcGCQq1tj5mKqrZBpQ4yx"
:local apiBase "https://honneycole.oyalo.net/api/router"
:local hdrs ("X-Router-ID: ".$routerId.",X-Router-Token: ".$routerToken)

:log info "Oyalo: downloading sync file..."
:local gotFile false

# Method 1: straight to file
:do {
    :local r [/tool fetch url=($apiBase."/commands") \
        http-method=get http-header-field=$hdrs \
        dst-path=oyalo-sync.rsc \
        check-certificate=no as-value]
    :log info ("Oyalo: file fetch status = ".($r->"status"))
    :if (($r->"status") = "finished") do={ :set gotFile true }
} on-error={
    :log warning "Oyalo: file fetch threw error - trying memory method"
}

# Method 2: in memory, then write to file
:if (!$gotFile) do={
    :do {
        :local r [/tool fetch url=($apiBase."/commands") \
            http-method=get http-header-field=$hdrs \
            output=user as-value check-certificate=no]
        :log info ("Oyalo: memory fetch status = ".($r->"status")." len=".[:len ($r->"data")])
        :if (($r->"status") = "finished") do={
            :do { /file remove [find where name="oyalo-sync.rsc"] } on-error={}
            /file add name=oyalo-sync.rsc contents=($r->"data")
            :set gotFile true
        }
    } on-error={
        :log warning "Oyalo: memory fetch threw error too"
    }
}

:if (!$gotFile) do={
    :log warning "Oyalo: download failed both ways - check route deployment / DNS / WAF"
    :stop
}

:do {
    /import file-name=oyalo-sync.rsc
    :log info "Oyalo: import finished OK"
} on-error={
    :log warning "Oyalo: import error (queue retries next run)"
}
:do { /file remove [find where name="oyalo-sync.rsc"] } on-error={}
:log info "Oyalo: file deleted"