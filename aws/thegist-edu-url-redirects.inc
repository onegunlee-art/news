    # EDU URL redirects — edu.thegist.co.kr 전용 (www/thegist 미적용)
    # location / { ... } 보다 위에 두어 exact match 우선
    location = / {
        return 302 /edu;
    }
    location = /admin {
        return 302 /edu/admin;
    }
