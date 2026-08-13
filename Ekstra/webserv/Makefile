NAME	:= webserv
CC	:= cc
CFLAGS	:= -Wall -Wextra -Werror -O2 -Iinclude
SRC	:= src/main.c src/http.c
OBJ	:= $(SRC:.c=.o)

all: $(NAME)

$(NAME): $(OBJ)
	$(CC) $(CFLAGS) -o $@ $(OBJ)

%.o: %.c include/webserv.h
	$(CC) $(CFLAGS) -c $< -o $@

run: $(NAME)
	./$(NAME) 8080 www

test: $(NAME)
	bash tests/test.sh

clean:
	rm -f $(OBJ)

fclean: clean
	rm -f $(NAME)

re: fclean all

.PHONY: all run test clean fclean re
