_phun__module_list(){
    local modules
    if [ -d "$(pwd)/modules" ]; then
        for module in `command ls -1 "$(pwd)/modules"`; do
            if [ -d "$(pwd)/modules/$module" ]; then
                local found=0
                for arg in "${COMP_WORDS[@]}"; do
                    if [[ $arg = $module ]]; then
                        found=1
                        break
                    fi
                done
                if [[ $found = 0 ]]; then
                    modules="$modules $module"
                fi
            fi
        done
    fi

    echo "$modules";
}

_phun(){
    local cur cmd
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    cmd="${COMP_WORDS[1]}"
    arglen="$COMP_CWORD"
    options="-h --help -v --version create model sync watch"

    case "${cmd}" in

        -h|--help|-v|--version|create)
            return 0
            ;;
        
        model)
            if [[ $arglen = 2 ]]; then
                COMPREPLY=( $(compgen -W "$(_phun__module_list)" -- ${cur}) )
            fi
            
            return 0
            ;;

        sync|watch)
            if [[ $arglen = 2 ]]; then
                COMPREPLY=( $(compgen -W "$(_phun__module_list)" -- ${cur}) )
            elif [[ $arglen = 3 ]]; then
                _filedir
            elif [[ $arglen = 4 ]]; then
                COMPREPLY=( $(compgen -W "update install" -- ${cur}) )
            fi

            return 0
            ;;

        *)
            COMPREPLY=( $(compgen -W "${options}" -- ${cur}) )
            ;;
    esac
}

complete -F _phun phun